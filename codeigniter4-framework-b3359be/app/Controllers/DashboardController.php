<?php
namespace App\Controllers;
use App\Models\MedicationModel;
final class DashboardController extends BaseController {
    private MedicationModel $medications;
    public function __construct() { $this->medications = new MedicationModel(); }
    public function index() { return view('dashboard/index', $this->medications->getDashboardData()); }
}
