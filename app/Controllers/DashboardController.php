<?php
namespace App\Controllers;
final class DashboardController extends BaseController {
 public function index(){ $db=db_connect(); return view('dashboard/index',['stats'=>[
  'medicaments'=>(int)($db->query('SELECT COALESCE(SUM(Nombres),0) total FROM medicament WHERE id_client IS NOT NULL')->getRow()->total ?? 0),
  'clients'=>(int)($db->query('SELECT COUNT(*) total FROM client')->getRow()->total ?? 0),
  'types'=>(int)($db->query('SELECT COUNT(DISTINCT Type) total FROM medicament WHERE id_client IS NOT NULL')->getRow()->total ?? 0),
  'mois'=>(int)($db->query("SELECT COUNT(DISTINCT DATE_FORMAT(Date, '%Y-%m')) total FROM medicament WHERE Date != '0000-00-00 00:00:00'")->getRow()->total ?? 0),
 ]]); }
}
