<?php
namespace App\Controllers; use App\Models\ReportingModel;
final class DashboardController extends BaseController {
 public function index(){return view('dashboard/index',['stats'=>(new ReportingModel())->homeStats(),'title'=>'Accueil']);}
}
