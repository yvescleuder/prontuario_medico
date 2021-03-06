<?php

namespace App\Controllers;

use App\Models\Medico as Medico;
use App\Models\Paciente as Paciente;
use App\Models\Secretaria;
use App\Models\Usuario;
use App\Requests\UsuarioLoginRequest;
use App\Requests\UsuarioRegisterRequest;
use Klein\Request;
use Klein\Response;

class UsuarioController extends Controller
{
	private $usuario;

    /**
     * UsuarioController constructor.
     */
    public function __construct()
	{
		parent::__construct();
		$this->usuario = new Usuario();
	}

    /**
     * Efetua o login e verifica qual é o tipo de usuário que está autenticando no sistema
     * @param \Klein\Response $response
     * @return string
     */
	public function login(Response $response) : string
    {
        $user = $_POST['user'];
        $rules = UsuarioLoginRequest::rules($user);
        if($rules !== true) {
            $response->code(302);
            $this->setResponse($rules);
        } else {
            $user['password'] = $this->encryption($user['password']);
            if(isset($user['crm'])) {
                $class = new Medico();
                $column = 'crm';
            } else {
                if($user["type"] == "paciente") {
                    $class = new Paciente();
                } else if($user["type"] == "secretaria") {
                    $class = new Secretaria();
                } else {
                    $class = new Paciente();
                }
                $column = 'email';
            }

            if($user = $class->login($user["$column"], $user["password"])) {
                    $_SESSION['user'] = $user;
                    $this->setResponse(["Logado com sucesso!"]);
            } else {
                    $response->code(302);
                    $this->setResponse(["error" => ["Falha ao autenticar os seus dados!"]]);
            }
        }

        return json_encode($this->getResponse());
    }

    /**
     * Transforma uma string para criptografia md5
     * @param string $string
     * @return string
     */
    private function encryption(string $string) : string
    {
	    return md5($string);
    }

    /**
     * @param array $usuario
     * @return array|string
     */
    public function create(array $usuario)
    {
        return $this->usuario->create($usuario);
    }

    /**
     * Valida o CPF de uma pessoa
     * @param string $cpf
     * @return bool
     */
    private function cpfValidator(string $cpf)
    {
        // Verifica se foi informado todos os digitos corretamente
        if(strlen($cpf) != 11)
            return false;

        // Verifica se foi informada uma sequência de digitos repetidos. Ex: 111.111.111-11
        if(preg_match('/(\d)\1{10}/', $cpf))
            return false;

        // Faz o calculo para validar o CPF
        for($t = 9; $t < 11; $t++) {
            for($d = 0, $c = 0; $c < $t; $c++)
                $d += $cpf{$c} * (($t + 1) - $c);
            $d = ((10 * $d) % 11) % 10;
            if($cpf{$c} != $d)
                return false;
        }
        return true;
    }

    /**
     * Efetua o logout de qualquer usuário
     * @param Response $response
     * @return \Klein\AbstractResponse
     */
    public function logout(Response $response)
    {
        session_destroy();
        return $response->redirect('/login', 200);
    }

    /**
     * Faz a validação de dados de um usuário
     * @param array $usuario
     * @return array|string
     */
    public function validation(array $usuario)
    {
        // CPF somente números;
        $usuario["cpf"] = preg_replace( '/[^0-9]/is', '', $usuario["cpf"]);
        $rules = UsuarioRegisterRequest::rules($usuario);
        if($rules !== true) {
            $this->setResponse(["success" => false, "msg" => $rules]);
        } else {
            $usuario['senha'] = $this->encryption($usuario['senha']);
            empty($usuario['naturalidade']) ? $usuario['naturalidade'] = NULL : '';
            !isset($usuario['email']) ? $usuario['email'] = NULL : '';
            if($this->usuario->valueExist("pessoa", "cpf", $usuario["cpf"])) {
                $this->setResponse(["success" => false, "msg" => [["CPF já cadastrado!"]]]);
            } else if($this->usuario->valueExist("pessoa","rg", $usuario["rg"])) {
                $this->setResponse(["success" => false, "msg" => [["RG já cadastrado!"]]]);
            } else if(!$this->cpfValidator($usuario['cpf'])) {
                $this->setResponse(["success" => false, "msg" => [["CPF Inválido!"]]]);
            } else if($usuario['email'] != NULL) {
                if($this->usuario->valueExist("pessoa","email", $usuario["email"])) {
                    $this->setResponse(["success" => false, "msg" => [["E-mail já cadastrado!"]]]);
                } else {
                    $this->setResponse(["success" => true, "msg" => $usuario]);
                }
            } else {
                $this->setResponse(["success" => true, "msg" => $usuario]);
            }
        }

        return $this->getResponse();
    }

    /**
     * Desativa um usuário no sistema
     * @param Request $request
     * @return int
     */
    public function disable(Request $request)
    {
        return $this->usuario->disable($request->id);
    }

    /**
     * Ativa um usuário no sistema
     * @param Request $request
     * @return int
     */
    public function active(Request $request)
    {
        return $this->usuario->active($request->id);
    }
}