# EZEV FRONTEND DATA CONTRACT & SHARED CLIENT LAYER (FROZEN)

> **Document Status:** FROZEN / ARCHITECTURAL SPECIFICATION  
> **Baseline Commit:** 89a7cc43aa92557f8dc42e99daa87550486cd489  
> **Target Branch:** develop/ezev-v1  
> **Implementation Phase:** Phase 4.0.1  
> **API Verification:** Grounded in actual Core & Operations backend implementations

---

## 1. Architectural Principles & Data Sourcing

1. **Cam truy van database truc tiep tu Theme:** Theme va frontend tuyet doi **khong su dung `$wpdb`** truc tiep cho bat ky truy van nghiep vu tram sac, phien sac, to chuc hoac du lieu van hanh nao.
2. **Kien truc Data Layer chuan:**
   - **Browser / Client-side:** Consume REST API thong qua SDK abstraction layer (`EzevDataClient`).
   - **WordPress Server-side Theme Templates:** Su dung public Plugin Service Layer (`EZEV_Core_Stations`, `EZEV_Core_Domain`) hoac REST API response.
   - **Never:** Raw `$wpdb` queries tren theme files.
3. **Hai tang du lieu P0 (Two-Tier Architecture):**
   - **Tang 1 - Public Baseline (San sang 100%):** 4 trang P0 (`/`, `/find-a-charger`, `/stations/[slug]`, `/charging-network`) la unauthenticated public pages, hoat dong hoan chinh dua tren **Core Station DTO** (`GET /ezev/v1/stations` va `GET /ezev/v1/stations/{station_id}`). Ban do Google Maps, tim kiem, danh sach tram va chi tiet deu chay hoan toan tu tang du lieu cong khai nay.
   - **Tang 2 - Public Live Enrichment (Future Projection API):** Cac endpoint Operations hien tai (`/overview`, `/chargers`, `/connectors`, `/sessions`, `/alerts`) deu yeu cau Authenticated Login. P0 khoi dau su dung manual/demo availability co san tu Core. Endpoint live availability cong khai an toan (`GET /ezev-ops/v1/public/availability`) la **PLANNED FOR FUTURE ENRICHMENT**.

---

## 2. Core Station DTO (Public Baseline)

Moi phan tu tram sac tra ve tu `GET /ezev/v1/stations` va `GET /ezev/v1/stations/{station_id}` tuan thu schema DTO bat bien sau:

```typescript
interface StationDTO {
  station_id: string;              // e.g. "EZEV-VN-SGN-001" (Stable string identifier)
  slug: string;                    // e.g. "tram-sac-landmark-81" (SEO URL slug)
  name: string;                    // e.g. "Landmark 81 Fast Charging Hub"
  description: string;             // Text mo ta tram
  address: {
    line: string;                  // Dia chi chi tiet
    city: string;                  // e.g. "Ho Chi Minh City"
    region: string;                // e.g. "Binh Thanh"
    country: string;               // e.g. "Vietnam"
    country_code: string;          // e.g. "VN"
  };
  location: {
    lat: number;                   // Toa do vi do Google Maps
    lng: number;                   // Toa do kinh do Google Maps
  };
  connectors: string[];            // e.g. ["CCS2", "Type 2", "CHAdeMO"]
  max_power_kw: number;            // e.g. 180.0
  ports: {
    total: number;                 // Tong so cong sac
    available: number;             // So cong sac ranh (Core manual baseline)
  };
  opening_hours: string;           // e.g. "24/7"
  status: string;                  // "active" | "maintenance" | "offline"
  amenities: string[];             // e.g. ["wifi", "coffee", "restroom", "parking"]
  data: {
    mode: "api" | "manual" | "demo";
    is_demo: boolean;
  };
  ownership: {
    organization_id: string;       // Stable org ref
    site_id: string;               // Stable site ref
  };
  public_notes: string;
  url: string;                     // Canonical permalink
  thumbnail: string;               // URL anh dai dien tram
  updated_at: string | null;       // ISO 8601 UTC timestamp
}
```

---

## 3. Station Collection Query Contract

- **Endpoint:** `GET /wp-json/ezev/v1/stations`
- **Query parameters thuc su duoc backend ho tro:**
  - `country` (optional string): Loc theo ma quoc gia (vi du: `?country=VN`, `?country=PH`).
- **Client-side Filtering Strategy cho P0:**
  - Voi quy mo P0 ban dau (~60 stations), toan bo danh sach tram duoc tai mot lan qua `GET /ezev/v1/stations` va cache phia client.
  - Trang **Find a Charger** thuc hien client-side filtering tuc thi (instant search/filter) cho:
    - `city` (loc theo thanh pho)
    - `connector` (chuan cong sac CCS2, Type 2, GB/T...)
    - `power` (cong suat toi thieu kW)
    - `status` (tinh trang hoat dong manual baseline)
  - Ham `getNearbyStations(lat, lng)` tinh toan khoang cach dia ly tren client su dung cong thuc **Haversine Formula** tu danh sach tram da cache, khong gui query ban kinh len backend.
  - Cac query nang cao nhu geospatial radius (`lat`, `lng`, `radius_km`) hoac server pagination (`page`, `per_page`) duoc danh dau la **PLANNED FOR SCALE** khi mang luoi dat hang tram tram.

---

## 4. Station Detail Slug Resolver

Route public cua trang chi tiet tram sac la:
```text
/stations/[slug]
```

### Quy trinh giai quyet (Resolver Flow):
1. WordPress Custom Post Type `ezev_station` tu dong nhan dien `slug` tu rewrite rule cua WordPress core.
2. **Server-side Resolution:** Trong template WordPress (`single-ezev_station.php` hoac custom template), theme su dung Core Station Service:
   ```php
   // Cach 1: Tu post hien tai
   $post_id = get_the_ID();
   $station_id = get_post_meta($post_id, '_ezev_station_id', true);

   // Cach 2: Tu slug thong qua Core Helper
   $station_id = EZEV_Core_Stations::get_station_id_by_slug($slug);
   ```
3. **Client-side Resolution:** StationDTO tra ve tu REST API da duoc tich hop truong `slug`. Frontend co the tra cuu 2 chieu giua `slug` va `station_id`.
4. **Cam dung `$post_id` lam business identity:** Toan bo API goi tiep theo, chuc nang Saved Stations, va tuong tac ban do deu chi su dung **`station_id`**.

---

## 5. Pricing & Billing Policy

- **Trang thai:** **NOT IMPLEMENTED / OPTIONAL CMS CONTENT**.
- Backend hien tai chua co module billing/tariff cho public P0.
- Theme khong hardcode gia gia (vi du: khong ghi cung "3.850 VND/kWh"), khong tu tinh bieu phi. Thong tin gia sac tren trang Station Detail neu co hien thi se la thong tin tinh tu CMS hoac truong `public_notes`.

---

## 6. Frontend Client SDK Contract (`EzevDataClient`)

```typescript
interface EzevDataClient {
  /** Lay danh sach tat ca cac tram cong khai tu Core API */
  getStations(countryCode?: string): Promise<StationDTO[]>;

  /** Lay chi tiet mot tram theo station_id */
  getStation(stationId: string): Promise<StationDTO>;

  /** Tim kiem tram lan can dua tren toa do nguoi dung (Haversine client calculation) */
  getNearbyStations(userLat: number, userLng: number, radiusKm?: number): Promise<StationDTO[]>;

  /** Xac thuc va lay thong tin thu moi hop le */
  verifyInvitation(token: string): Promise<{
    valid: boolean;
    invitation_id: string;
    email: string;
    organization_id: string;
    organization_name: string;
    role_key: string;
    expires_at: string;
  }>;
}
```

---

## 7. Metadata Presentation Standards

Moi giao dien the hien trang thai van hanh cua tram phai tuan theo bang quy chuan sau:

| `data_mode` | `is_stale` | UI Badge / Label | Mau sac UI | Giai thich y nghia |
| :--- | :---: | :--- | :--- | :--- |
| `api` | `false` | `Live Data (Updated Xm ago)` | Xanh la (Green) | Du lieu live tu he thong truyen tin API/SCADA |
| `api` | `true` | `Telemetry Delayed (Xm ago)` | Vang (Amber) | Du lieu API bi gian doan tin hieu tam thoi |
| `manual` | `any` | `Manual Data (Updated Xm ago)` | Xam / Xanh duong nhe | Du lieu do nhan vien van hanh ghi nhan bang tay |
| `demo` | `any` | `Demo Simulation Mode` | Tim nhat (Purple) | Du lieu mo phong phuc vu kiem thu/demo |