<?php
namespace App\Controllers; use App\Models\UserModel;
final class UsersController extends BaseController { public function index(){ return view('pages/users',['users'=>(new UserModel())->orderBy('user_id','DESC')->findAll(),'title'=>'Utilisateurs']); } }
