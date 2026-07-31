<?php
namespace App\Models;
use CodeIgniter\Model;
final class MedicationModel extends Model {
    protected $table = 'medicament'; protected $primaryKey = 'id';
    protected $allowedFields = ['Type','Nombres','id_client','Date','date'];
}
