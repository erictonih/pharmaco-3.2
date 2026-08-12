<?php

namespace App\Controllers;

use App\Models\ClientModel;
use App\Models\MedicationModel;
use App\Models\RegionModel;

final class DataController extends BaseController
{
    public function table() { return view('data/table', ['rows' => (new MedicationModel())->records()]); }
    public function charts() { return view('data/charts', ['charts' => (new MedicationModel())->chartData()]); }
    public function analysis() { return view('data/analysis', ['districts' => (new MedicationModel())->byDistrict()]); }
    public function tdc() { return view('data/tdc'); }
    public function api() { return $this->response->setJSON((new MedicationModel())->records()); }
    public function regions() { return $this->response->setJSON((new RegionModel())->all()); }
    public function districts(int $regionId) { return $this->response->setJSON((new RegionModel())->districts($regionId)); }
}
