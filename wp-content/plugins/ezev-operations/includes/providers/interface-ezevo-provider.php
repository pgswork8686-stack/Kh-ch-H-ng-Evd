<?php
if (!defined('ABSPATH')) { exit; }
interface EZEV_Operations_Provider {
    public function key(): string;
    public function label(): string;
    public function mode(): string;
    public function test_connection(): array;
    public function fetch_chargers(): array;
    public function fetch_sessions(): array;
    public function fetch_energy(): array;
    public function fetch_alerts(): array;
}
