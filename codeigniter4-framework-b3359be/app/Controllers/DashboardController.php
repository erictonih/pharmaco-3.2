<?php

namespace App\Controllers;

use App\Models\MedicationModel;

final class DashboardController extends BaseController
{
    public function index() { return view('dashboard/index', ['stats' => (new MedicationModel())->dashboard()]); }
}
