# EZEV FRONTEND DATA CONTRACT & SHARED CLIENT LAYER (FROZEN)

> **Document Status:** FROZEN / ARCHITECTURAL SPECIFICATION  
> **Baseline Commit:** 3d20ec28c75249669a08bced491e3943dd542837  
> **Target Branch:** develop/ezev-v1  
> **Implementation Phase:** Phase 4.0

---

## 1. Architectural Principles

1. **Cam truy van database truc tiep tu Theme:** Theme va Frontend tuyet doi **khong duoc su dung $wpdb** hoac truy van truc tiep vao database cho du lieu nghiep vu tram sac, phien sac, hoac to chuc.
2. **REST API la Single Source of Data:** Toan bo du lieu hien thi phai duoc consume thong qua REST API chuan cua ezev-core (/wp-json/ezev/v1/*) va ezev-operations (/wp-json/ezev-ops/v1/*).
3. **Shared Frontend Data Layer:** Cac trang khong duoc tu code logic goi API rieng le, ma phai thong qua **Client SDK / Shared Abstraction Library**.

---

## 2. P0 Core Pages Data Dependency Matrix

### 2.1 01. Home (/)
- **Data Dependencies:**
  - Network Statistics (KPIs): Tong so tram, tong so cong sac, tong san luong sạc (kWh), so thanh pho/quoc gia phu song.
  - Featured Stations / Nearby Stations: Danh sach tram tieu bieu hoac tram gan nhat neu nguoi dung cho phep Geolocation.
  - Mini Google Map: Marker tong quan ve mat do tram.
- **REST Endpoints:**
  - GET /wp-json/ezev/v1/stations?per_page=6 (Danh sach tram cong khai)
  - GET /wp-json/ezev-ops/v1/overview (So lieu thong ke mang luoi toan he thong)
- **Maps Dependency:** Mini Google Map (interactive nhe, click marker mo popup thong tin tram kem nut dan duong hoac xem chi tiet).

### 2.2 02. Find a Charger (/find-a-charger)
- **Data Dependencies:**
  - Station Master List: Toa do (lat, lng), ten tram, dia chi, hotline, hinh anh, tien ich xung quanh (amenities).
  - Live Operational Availability: Tinh trang ranh/ban cua tung cong sac (Available, Charging, Faulted, Offline).
  - Location Search: Google Places Autocomplete API.
  - Client Geolocation: HTML5 Geolocation API (
avigator.geolocation.getCurrentPosition).
- **REST Endpoints:**
  - GET /wp-json/ezev/v1/stations?per_page=100 (Core station master data)
  - GET /wp-json/ezev-ops/v1/chargers (Thiet bi sac & trang thai live)
  - GET /wp-json/ezev-ops/v1/connectors (Chi tiet chuan cong CCS2, Type 2, CHAdeMO va cong suat kW)
- **Maps Dependency:** Full Google Maps JavaScript API voi Marker Clustering, InfoWindow, Search This Area event, va List-to-Marker synchronize.

### 2.3 03. Station Detail (/stations/[slug])
- **Data Dependencies:**
  - Station Entity: Toa do, dia chi day du, mo ta, anh thuc te, gio hoat dong (Opening hours).
  - Chargers & Connectors: Danh sach tru sac, so hieu dau sac, chuan cong, cong suat toi da (kW).
  - Live Status & Pricing: Gia dien sac (VND/kWh), phi dich vu, tinh trang san sang hien tai.
  - Operations Metadata: Nguon du lieu va do tuoi thong tin:
    - data_mode: (pi, manual, demo)
    - data_source: (juhang_api, evd_scada, manual_operator, demo_seed)
    - last_updated: Timestamp cap nhat lan cuoi
    - reshness_seconds: Do tuoi cua du lieu (tinh theo giay)
    - is_stale: Boolean (true neu qua 900s chua co cap nhat moi)
  - Nearby Stations: 3-4 tram gan nhat trong ban kinh 10-20km.
- **REST Endpoints:**
  - GET /wp-json/ezev/v1/stations/{station_id}
  - GET /wp-json/ezev-ops/v1/chargers?station_id={station_id}
  - GET /wp-json/ezev-ops/v1/connectors
  - GET /wp-json/ezev/v1/stations?lat={lat}&lng={lng}&radius_km=15 (Nearby stations)
- **Display Requirement:** Phai hien thi ro rang badge trang thai du lieu (vi du: *"Manual data - Updated 12 minutes ago"* hoac *"Demo Mode"*). Tuyet doi khong trinh bay du lieu Demo/Manual nhu la realtime SCADA API.

### 2.4 04. Charging Network (/charging-network)
- **Data Dependencies:**
  - Regional & Country Aggregates: So luong tram va cong sac theo tinh/thanh pho (Ha Noi, Da Nang, TP.HCM, Can Tho, Binh Duong...) va quoc gia (Viet Nam, Philippines, China).
  - Hardware Capability Distribution: Ti le tram sieu nhanh (DC Fast >120kW) vs tram tieu chuan (AC 11-22kW).
  - Macro Map View: Google Map pham vi toan quoc/khu vuc voi ban do nhiet (Heatmap) hoac cuom cum khu vuc (Cluster bubbles).
- **REST Endpoints:**
  - GET /wp-json/ezev/v1/stations?per_page=200
  - GET /wp-json/ezev-ops/v1/overview
  - GET /wp-json/ezev-ops/v1/reports/summary
- **Maps Dependency:** Macro Google Map voi zoom level toan quoc (Zoom 5-6), khong chi tiet hoa tung dau sac nhu trang Find a Charger ma tap trung vao quy mo va phu song.

---

## 3. Frontend Client Abstraction Layer (SDK Contract)

Trong theme/frontend, toan bo logic giao tiep voi backend se duoc tap trung trong module EzevDataClient:

`	ypescript
interface EzevDataClient {
  /** Lay danh sach tat ca cac tram cong khai kem bo loc */
  getStations(params?: {
    country?: string;
    city?: string;
    connector_type?: string;
    min_power?: number;
    search?: string;
    page?: number;
    per_page?: number;
  }): Promise<{ stations: StationDTO[]; pagination: PaginationMeta }>;

  /** Lay chi tiet mot tram theo station_id */
  getStation(stationId: string): Promise<StationDTO>;

  /** Lay thong tin van hanh thoi gian thuc cua mot tram */
  getStationOperations(stationId: string): Promise<StationOperationsDTO>;

  /** Lay danh sach thiet bi sac thuoc tram */
  getChargers(stationId: string): Promise<ChargerDTO[]>;

  /** Lay danh sach dau sac */
  getConnectors(chargerId?: string): Promise<ConnectorDTO[]>;

  /** Tim kiem tram lan can theo toa do dia ly */
  getNearbyStations(lat: number, lng: number, radiusKm?: number): Promise<StationDTO[]>;

  /** Lay chi so tong quan mang luoi (KPIs) */
  getNetworkStats(): Promise<NetworkOverviewDTO>;
}
`

---

## 4. Metadata Presentation Standards

Moi giao dien the hien trang thai hoat dong cua tram phai tuan theo bang quy chuan sau:

| data_mode | is_stale | UI Badge / Label | Mau sac UI | Giai thich y nghia |
| :--- | :---: | :--- | :--- | :--- |
| pi | alse | Live Data (Updated Xm ago) | Xanh la (Green) | Du lieu live tu he thong truyen tin API/SCADA |
| pi | 	rue | Telemetry Delayed (Xm ago) | Vang (Amber) | Du lieu API bi gian doan tin hieu tam thoi |
| manual | ny | Manual Data (Updated Xm ago) | Xam / Xanh duong nhe | Du lieu do nhan vien van hanh ghi nhan bang tay |
| demo | ny | Demo Simulation Mode | Tim nhat (Purple) | Du lieu mo phong phuc vu kiem thu/demo |
