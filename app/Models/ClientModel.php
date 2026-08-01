<?php
namespace App\Models;
use CodeIgniter\Model;
final class ClientModel extends Model {
    protected $table = 'client'; protected $primaryKey = 'Id'; protected $useAutoIncrement = false;
    protected $allowedFields = ['Id','Nom','Prenom','Age','Genre','Type','Residence','Region','District','date_de_sousmission'];
}
