<?php
namespace App\Controllers;
final class UsersController extends BaseController { public function index(){ return view('pages/users',['users'=>db_connect()->table('utilisateurs')->orderBy('user_id','DESC')->get()->getResultArray()]); } }
