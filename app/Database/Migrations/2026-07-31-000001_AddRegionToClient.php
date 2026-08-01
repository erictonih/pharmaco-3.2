<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;
final class AddRegionToClient extends Migration {
 public function up(){ $this->forge->addColumn('client',['Region'=>['type'=>'VARCHAR','constraint'=>100,'null'=>true,'after'=>'Residence']]); }
 public function down(){ $this->forge->dropColumn('client','Region'); }
}
