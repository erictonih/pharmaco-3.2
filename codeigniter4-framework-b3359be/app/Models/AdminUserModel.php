<?php
namespace App\Models;
use App\Libraries\PdoConnection;
final class AdminUserModel {
 public function stats(): array { $p=PdoConnection::get(); return ['total'=>(int)$p->query('SELECT COUNT(*) FROM utilisateurs')->fetchColumn(),'actifs'=>(int)$p->query("SELECT COUNT(*) FROM utilisateurs WHERE statut='Actif'")->fetchColumn(),'inactifs'=>(int)$p->query("SELECT COUNT(*) FROM utilisateurs WHERE statut='Inactif'")->fetchColumn()]; }
 public function list(string $search='', string $role=''): array { $q='SELECT user_id,username,prenom,nom,email,role,statut,derniere_connexion FROM utilisateurs WHERE 1=1';$v=[];if($search!==''){$q.=' AND (username LIKE :search_username OR nom LIKE :search_nom OR prenom LIKE :search_prenom OR email LIKE :search_email)';$term='%'.$search.'%';$v=['search_username'=>$term,'search_nom'=>$term,'search_prenom'=>$term,'search_email'=>$term];}if($role!==''){$q.=' AND role=:role';$v['role']=$role;}$q.=' ORDER BY user_id DESC';$s=PdoConnection::get()->prepare($q);$s->execute($v);return $s->fetchAll(); }
 public function create(array $v): void {$s=PdoConnection::get()->prepare('INSERT INTO utilisateurs (username,prenom,nom,email,role,statut,mdp) VALUES (:username,:prenom,:nom,:email,:role,:statut,:mdp)');$s->execute($v);}
 public function toggle(int $id): void {PdoConnection::get()->prepare("UPDATE utilisateurs SET statut=IF(statut='Actif','Inactif','Actif') WHERE user_id=:id")->execute(['id'=>$id]);}
 public function update(int $id,array $v): void {$s=PdoConnection::get()->prepare('UPDATE utilisateurs SET prenom=:prenom,nom=:nom,email=:email,role=:role WHERE user_id=:id');$v['id']=$id;$s->execute($v);}
 public function delete(int $id): void {PdoConnection::get()->prepare('DELETE FROM utilisateurs WHERE user_id=:id')->execute(['id'=>$id]);}
}
