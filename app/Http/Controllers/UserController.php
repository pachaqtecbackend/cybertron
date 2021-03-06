<?php

namespace Cybertron\Http\Controllers;

use Cybertron\User;
use Cybertron\Role;
use Cybertron\UsersJob;
use Cybertron\UsersStudies;
use Cybertron\UsersCertificate;
use Cybertron\UserInformation;
use Cybertron\UsersExperience;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class UserController extends CybertronController
{
    public function __construct(){
        $this->middleware('auth');
    }

    public function ViewUsers(Request $request){
        $response = $request->user()->authorizeRoles(['admin']);
        if($response) return view('elements/Users/listUsers');
        return view('errors/permission');
    }

    public function formUser(Request $request){
        $response = $request->user()->authorizeRoles(['admin']);
        if($response) {
            if($request->valueId == null){
                return view('elements/formularios/formUser')->with(array(
                    'updateForm'    => false,
                    'updatePass'    => false,
                ));
            }else{
                return view('elements/formularios/formUser')->with(array(
                    'updateForm'    => true,
                    'updatePass'    => false,
                    'id'            => $request->valueId
                ));
            }
        }
        return view('errors/permission');
    }

    public function formUserPass(Request $request){
        $response = $request->user()->authorizeRoles(['admin']);
        if($response) {
            return view('elements/formularios/formUser')->with(array(
                'updateForm'    => true,
                'updatePass'    => true,
                'id'            =>  $request->valueId
            ));
        }
        return view('errors/permission');
    }

    public function bioUser(Request $request){
        $response = $request->user()->authorizeRoles(['admin']);
        if($response) {
            return view('elements/Users/bioUser')->with(array(
                'id'            =>  $request->valueId
            ));
        }
        return view('errors/permission');
    }

    public function viewJobs(Request $request){
        if ($request->isMethod('get')) {
            $resultado = UsersJob::Select()
                ->orderby('name_job','asc')
                ->get()
                ->toArray();
        }

        return $resultado;
    }

    public function updateUser(Request $request){
        if ($request->isMethod('get')) {
            $resultado = User::Select()
                ->where('users.id', $request->idUser)
                ->leftJoin('users_jobs', 'users_jobs.id', '=', 'users.id_job')
                ->leftJoin('users_information', 'users_information.user_id', '=', 'users.id')
                ->get()
                ->toArray();
        }
        return $resultado;
    }

    public function saveUser(Request $request){
        if ($request->isMethod('post')) {

            $id_job = UsersJob::Select()
                        ->where('name_job', $request->typeUser)
                        ->get()
                        ->toArray();

            if($request->idUser == null){
                $this->validate(request(), [
                    'userRed'               => 'required',
                    'typeUser'              => 'required',
                    'Document'              => 'required',
                    'numberDocument'        => 'required'
                ]);

                $user = User::create([
                    'username'      => Str::lower($request->userRed),
                    'password'      => bcrypt(Str::lower($request->userRed)),
                    'email'         => Str::lower($request->userRed).'@sapia.com.pe',
                    'status_id'     => '1',
                    'id_job'        => $id_job[0]['id']
                ]);

                $user
                    ->roles()
                    ->attach(Role::where('name', 'user')->first());

                UserInformation::updateOrInsert([
                    'user_id' => $user->id
                ], [
                    'identity'          => $request->Document,
                    'identity_number'   => $request->numberDocument
                ]);
            }else{
                if($request->passUser == null){
                    $this->validate(request(), [
                        'userRed'               => 'required',
                        'typeUser'              => 'required',
                        'Document'              => 'required',
                        'numberDocument'        => 'required'
                    ]);

                    User::where('id', $request->idUser)
                        ->update([
                            'username'  => Str::lower($request->userRed),
                            'id_job'    => $id_job[0]['id']
                        ]);

                    UserInformation::updateOrInsert([
                        'user_id' => $request->idUser
                    ], [
                        'identity'          => $request->Document,
                        'identity_number'   => $request->numberDocument
                    ]);
                }else{
                    $this->validate(request(), [
                        'userRed'        => 'required',
                        'passUser'       => 'required'
                    ]);

                    User::where('id', $request->idUser)
                        ->update([
                            'username'  => Str::lower($request->userRed),
                            'password'  => bcrypt(Str::lower($request->passUser)),
                            'id_job'    => $id_job[0]['id']
                        ]);
                }
            }

            return ['message' => 'Success'];
        }
        return ['message' => 'Error'];
    }

    public function changeStatus(Request $request){
        if ($request->isMethod('post')) {
            $this->validate(request(), [
                'idUser'           => 'required',
                'statusUser'       => 'required'
            ]);

            $status = ($request->statusUser == 1 ? 0 : 1);

            User::where('id', $request->idUser)
                    ->update([
                       'status_id' => $status
                    ]);

            return ['message' => 'Success'];
        }
        return ['message' => 'Error'];
    }

    public function listUsers(Request $request){
        if ($request->isMethod('post')) {
            $query_user_list        = $this->user_list_query();
            $builderview            = $this->builderview($query_user_list);
            $outgoingcollection     = $this->outgoingcollection($builderview);
            $list_users             = $this->FormatDatatable($outgoingcollection);
            return $list_users;
        }
    }

    protected function user_list_query(){
        $user_list_query = User::Select()
                            ->with('roles')
                            ->with('usersStudies')
                            ->with('usersInformation')
                            ->orderBy('first_last_name', 'asc')
                            ->get()
                            ->toArray();
        return $user_list_query;
    }

    protected function builderview($user_list_query,$type=''){
        $posicion = 0;
        $idList = 0;
        foreach ($user_list_query as $query) {
            $idList ++;
            $builderview[$posicion]['id']           = $idList;
            $builderview[$posicion]['id_user']      = $query['id'];
            $builderview[$posicion]['name']         = ucwords(Str::lower($query['name']));
            $builderview[$posicion]['last_name']    = ucwords(Str::lower($query['first_last_name'].' '.$query['second_last_name']));
            $builderview[$posicion]['username']     = $query['username'];
            $builderview[$posicion]['roles']        = ucwords($query['roles'][0]['name']);
            $builderview[$posicion]['status']       = $query['status_id'];
            $posicion ++;
        }

        if(!isset($builderview)){
            $builderview = [];
        }

        return $builderview;
    }

    protected function outgoingcollection($builderview){
        $outgoingcollection = new \Illuminate\Support\Collection;
        foreach ($builderview as $view) {

            $outgoingcollection->push([
                'id'            => $view['id'],
                'name'          => $view['name'],
                'last_name'     => $view['last_name'],
                'username'      => $view['username'],
                'roles'         => $view['roles'],
                'status'        => ($view['status'] == 1 ? 'Activo' : 'Cesado'),
                'action'        => '<div class="btn-group">
                                        <a class="btn btn-primary btnFix" onclick='.'updateModal("div.bodyviewUser","bioUser",'.$view["id_user"].')'.' data-toggle="modal" data-target=".modalviewUser"><i class="fa fa-eye"></i></a>
                                        <a class="btn btn-warning btnFix" onclick='.'updateModal("div.bodyUser","formUserUpdate",'.$view["id_user"].')'.' data-toggle="modal" data-target=".modalUser"><i class="fa fa-edit"></i></a>
                                        <a class="btn btn-default btnFix" onclick='.'updateModal("div.bodyUser","formPassUpdate",'.$view["id_user"].')'.' data-toggle="modal" data-target=".modalUser"><i class="fa fa-key"></i></a>
                                        <a class="btn btn-danger btnFix" onclick="changeStatus('.$view['id_user'].','.$view['status'].')"><i class="fa fa-ban"></i></a>
                                    </div>',
            ]);

        }
        return $outgoingcollection;
    }

    //Funciones para visualizar a Usuario
    public function viewProfile(Request $request){
        if ($request->isMethod('get')) {
            $resultado = User::Select()
                ->with('usersInformation')
                ->with('roles')
                ->where('id', $request->idUser)
                ->get()
                ->toArray();
        }
        return $resultado;
    }

    public function viewUserJob(Request $request){
        if ($request->isMethod('get')) {
            $resultado = User::Select()
                ->where('users.id', $request->idUser)
                ->join('users_jobs', 'users.id_job', '=', 'users_jobs.id')
                ->get()
                ->toArray();
        }
        return $resultado;
    }

    public function viewDatosAcademicos(Request $request){
        if ($request->isMethod('get')) {
            $resultado = UsersStudies::Select()
                ->where('user_id', $request->idUser)
                ->get()
                ->toArray();
        }
        return $resultado;
    }

    public function viewCertificaciones(Request $request){
        if ($request->isMethod('get')) {
            $resultado = UsersCertificate::Select()
                ->where('user_id', $request->idUser)
                ->get()
                ->toArray();
        }
        return $resultado;
    }

    public function viewExperiencias(Request $request){
        if ($request->isMethod('get')) {
            $resultado = UsersExperience::Select()
                ->where('user_id', $request->idUser)
                ->get()
                ->toArray();
        }
        return $resultado;
    }

    // Funciones para Subir Archivos
    public function formUpload(Request $request){
        $response = $request->user()->authorizeRoles(['user', 'admin']);
        if($response) {
            if ($request->isMethod('post')) {
                return view('elements/formularios/formUpload')->with(array(
                    'filesPermited'    => $request->filesPermited,
                    'nameUpload'       => $request->nameUpload,
                    'numberFiles'      => $request->numberFiles
                ));
            }
        }
        return view('errors/permission');
    }
}
