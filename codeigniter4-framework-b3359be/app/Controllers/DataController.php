<?php
namespace App\Controllers;
use App\Models\MedicationModel;
use App\Models\RegionModel;
final class DataController extends BaseController {
    private MedicationModel $medications; private RegionModel $regions;
    public function __construct()
     {
         $this->medications = new MedicationModel();
          $this->regions = new RegionModel();
           }
    public function table() { return view('data/table', $this->medications->getTablePageData()); }
    public function charts() { return view('data/charts', $this->medications->getChartsPageData()); }
    public function analysis() { return view('data/analysis', $this->medications->getAnalysisPageData()); }
    public function tdc() { return view('data/tdc'); }
    public function api() { return $this->response->setJSON($this->medications->getRecords()); }
    public function regions() { return $this->response->setJSON($this->regions->getRegions()); }
    public function districts(int $regionId) { return $this->response->setJSON($this->regions->getDistricts($regionId)); }
}
