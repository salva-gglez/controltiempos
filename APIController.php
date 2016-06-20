<?php

class APIController extends BaseController {

	private $terminal = NULL;

	public function __construct()
	{
        $name = Config::get('controltiempos.default_cookie_name');

        if( isset($_COOKIE[$name]) )
        {
            $this->terminal = $_COOKIE[$name];
        }
	}

	public function get_me()
	{
		if( Auth::guest() )
		{
			header("HTTP/1.1 401 Unauthorized action");
			echo 'Unauthorized action';
			exit;
		}

		$user = Auth::user();

		$data = (object) array(
			'user_item' => (object) array(
				'id' => $user->usu_id,
				'name' => $user->usu_name
			),
			'library_item' => null
		);

		$library_item = Biblioteca::find($user->usu_bib_id);
		if( !empty($library_item) )
		{
			$data->library_item = (object) array(
					'id' => $library_item->bib_id,
					'name' => $library_item->bib_name,
				);
		}

		return Response::json($data);
	}

	public function get_terminal_list()
	{
		if( Auth::guest() )
		{
			header("HTTP/1.1 401 Unauthorized action");
			echo 'Unauthorized action';
			exit;
		}

		$data = (object) array(
			'library_item' => null,
			'terminal_list' => array(),
		);

		$user = Auth::user();

		$library_item = Biblioteca::find($user->usu_bib_id);

		if( empty($library_item) )
		{
			header("HTTP/1.1 400 Bad request");
			echo 'El usuario no tiene relacionada una biblioteca.';
			exit;
		}

		$data->library_item = (object) array(
			'id' => $library_item->bib_id,
			'name' => $library_item->bib_name,
			'clientNum' => $library_item->bib_clientNum,
		);

		$client_num = $data->library_item->clientNum > 99 ? 99 : $data->library_item->clientNum;

		$list = DB::table('estado')
			->where('est_terminal', 'LIKE', 'T'. $data->library_item->id . '%')
			->get();

		$terminal_list = array();
		$keys = array();

		// Delete
		foreach( $list as $item )
		{
			$terminal_id = intval(substr($item->est_terminal, 4));
			if( $terminal_id > $data->library_item->clientNum )
			{
				$list = DB::table('estado')
					->where('est_terminal', '=', $item->est_terminal)
					->delete();
			}
			else
			{
				$terminal_list[] = $item;
				$keys[] = $item->est_terminal;
			}
		}

		// Insert new
		for( $i = 1; $i <= $client_num; $i++ )
		{
			if( $i < 10 )
			{
				$id = sprintf("T%03s%02s", $data->library_item->id, $i);
			}
			else
			{
				$id = sprintf("T%03s%s", $data->library_item->id, $i);
			}

			if( !in_array($id, $keys) )
			{
				$terminal = new Estado();
				$terminal->est_terminal = $id;
				$terminal->est_status = 0;
				$terminal->est_timetolive = 0;
				try
				{
					$terminal->save();
				}
				catch(Exception $e) { echo $e->getMessage();}

				$terminal_list[] = $terminal;
			}

		}

		foreach( $terminal_list as $item )
		{
			$terminal = (object) array(
				'id' => $item->est_terminal,
				'logon_time' => $item->est_logontime,
				'status' => empty($item->est_status) ? 0 : $item->est_status,
				'alive' => $item->est_alive,
				'timetolive' => $item->est_timetolive,
				'lector' => NULL
			);

			$lector = Lector::where('terminal', '=', $terminal->id)
				->first();
			if( isset($lector) )
			{
				$terminal->lector = (object) array(
					'id' => $lector->id,
					'username' => $lector->username,
					'nombre' => $lector->nombre);
			}

			$data->terminal_list[] = $terminal;
		}

		return Response::json($data);
	}

	public function get_terminal($terminal_id)
	{
		Log::debug('get_teminal');
		if( Auth::guest() )
		{
			header("HTTP/1.1 401 Unauthorized action");
			echo 'Unauthorized action';
			exit;
		}

		$user = Auth::user();

// TODO comprobar biblioteca relacionada

		$biblioteca = Biblioteca::find($user->usu_bib_id);

// TODO comprobar biblioteca relacionada
		$client_num = $biblioteca->bib_defaultTimeToLive;

		$item = Estado::find($terminal_id);

		Log::debug('est_status='. $item->est_status . ' est_timetolive=' . $item->est_timetolive . ' bib_default=' . $biblioteca->bib_defaultTimeToLive);
		// TODO validar que existe
		$data = (object) array(
			'terminal_item' => (object) array(
				'id' => $item->est_terminal,
				'logon_time' => $item->est_logontime,
				'status' => empty($item->est_status) ? 0 : $item->est_status,
				'alive' => $item->est_alive,

				// Si no está activa, por defecto se pone el de la biblioteca
				//'timetolive' => $item->est_status ? $item->est_timetolive : $biblioteca->bib_defaultTimeToLive,
				'timetolive' => $item->est_status ? $item->est_timetolive : intval($biblioteca->bib_defaultTimeToLive),
				'default_timetolive' => $biblioteca->bib_defaultTimeToLive
			)
		);
                
		return Response::json($data);
	}

        public function update_estado_terminal($terminal_id)
	{
		Log::debug("Actualizar estado terminal.");

		if( Auth::guest() )
                {
                        header("HTTP/1.1 401 Unauthorized action");
                        echo 'Unauthorized action';
                        exit;
                }

                $data = file_get_contents("php://input");
                $data = json_decode($data);

		$user = Auth::user();

		$item = Estado::find($terminal_id);
                $item->est_status = $data->status;
		$item->est_timetolive = 0;
                $item->est_alive = NULL;

		$item->save();

		$response = (object) array();
                return Response::json($response);
	}

	public function update_terminal($terminal_id)
	{
		Log::debug("Actualizar terminal.");
		if( Auth::guest() )
		{
			header("HTTP/1.1 401 Unauthorized action");
			echo 'Unauthorized action';
			exit;
		}

		$data = file_get_contents("php://input");
		$data = json_decode($data);

		Log::debug('Datos terminal (' . print_r($data, TRUE) . ').');
		if ($data->timetolive != 0) {
			if( empty($data->timetolive) )  // Si el valor = 0 empty es True
			{
				Log::debug("data->timetolive vacio.");
				header("HTTP/1.1 400 Bad Request");
				echo 'El campo Tiempo de uso no puede estar vacío.';
				//echo $e->getMessage();
				exit;
			}
		}
		if( !is_numeric($data->timetolive) )
		{
			Log::debug("datoa->timetolive no es numerico.");
			header("HTTP/1.1 400 Bad Request");
			echo 'El campo Tiempo de uso es incorrecto.';
			//echo $e->getMessage();
			exit;
		}
		Log::debug('Comprobaciones timetolive completadas');

		$user = Auth::user();

// TODO comprobar biblioteca relacionada

		$biblioteca = Biblioteca::find($user->usu_bib_id);
		Log::debug('Biblioteca terminal (' . $biblioteca->bib_defaultTimeToLive . ').');
// TODO comprobar biblioteca relacionada
		$client_num = $biblioteca->bib_defaultTimeToLive;

		$item = Estado::find($terminal_id);
		Log::debug('Estado terminal ( ' . $item->est_status . ').');
		$item->est_status = $data->status;

		if( $item->est_status )
		{
			$item->est_timetolive = $data->timetolive;
		}
		else
		{
			$item->est_timetolive = 0;
			$item->est_logontime = NULL;
			$item->est_alive = NULL;

            // Se desvincula el terminal del lector
            Lector::where('terminal', '=', $item->est_terminal)
                ->update(array('terminal' => NULL));
		}
		$item->save();

		$response = (object) array();

		return Response::json($response);
	}

	/**
	 * Buscar lectores
	 * Devuelve una lista de lectores
	 */
	public function search_lector_by_uid() {
		if( Auth::guest() )
		{
			header("HTTP/1.1 401 Unauthorized action");
			echo 'Unauthorized action';
			exit;
		}

		$data = file_get_contents("php://input");
		$params = json_decode($data);

		if( empty($params->uid) )
		{
			header("HTTP/1.1 400 Bad Request");
			echo 'Debe indicar el identificador del usuario a buscar.';
			echo $e->getMessage();
			exit;
		}

		$response = (object) array(
			'lector_list' => array());

		Log::debug("Buscando (" . $params->uid . ").");
		$results = DB::table('lector')
			->where('username', 'LIKE', '%' . $params->uid . '%')
		 	->orwhere('nombre', 'LIKE', '%' . $params->uid . '%')
                        ->orwhere('apellidos', 'LIKE', '%' . $params->uid . '%')
			->orwhere('dni', 'LIKE', '%' . $params->uid . '%')
    		->take(25)
    		->get();
		
		foreach( $results as $item )
		{
			$lector = (object) array(
				'id' => intval($item->id),
				'username' => $item->username,
				'origen' => intval($item->origen),
				'nombre' => $item->nombre,
				'ultimo_login' => $item->ultimo_login,
				'terminal' => $item->terminal,
				'apellidos' => $item->apellidos,
				'dni' => $item->dni,
				'correo' => $item->correo,
				'telefono' => $item->telefono,
				'notas' => $item->notas,
				'acumuladoDiario' => $item->acumuladoDiario,
				'acumuladoSemanal' => $item->acumuladoSemanal
			);
			$response->lector_list[] = $lector;
		}

		return Response::json($response);
	}

	/**
	 * Lector
	 * Devuelve una lista de lectores
	 */
	public function get_lector_by_id($lector_id) {
		if( Auth::guest() )
		{
			header("HTTP/1.1 401 Unauthorized action");
			echo 'Unauthorized action';
			exit;
		}

		try
		{
			if( empty($lector_id) )
			{
				throw new Exception('No se indicó el identificador del lector.');
			}
			$item = Lector::find($lector_id);

			if( empty($item) )
			{
				throw new Exception('No se encontró el lector.', 1);
			}

			$response = (object) array(
				'lector' => (object) array(
					'id' => $item->id,
					'username' => $item->username,
					'origen' => $item->origen,
					'nombre' => $item->nombre,
					'terminal' => $item->terminal,
					'ultimo_login' => $item->ultimo_login,
					'limite_diario' => intval($item->limite_diario),
					'limite_semanal' => intval($item->limite_semanal),
                	'apellidos' => $item->apellidos,
					'correo' => $item->correo,
					'dni' => $item->dni,
					'notas' => $item->notas,
					'telefono' => $item->telefono,
					'acumuladoDiario' => $item->acumuladoDiario,
					'acumuladoSemanal' => $item->acumuladoSemanal
				)
			);

			return Response::json($response);
		}
		catch(Exception $ex)
		{
			header("HTTP/1.1 400 Bad Request");
			if( $ex->getCode() == 1 )
			{
				echo $e->getMessage();
			}
			else {
				echo 'Ocurrió un error. Reinicie la página y vuelva a intentarlo.';
			}
			exit;
		}

		$response = (object) array(
			'lector_list' => array());

		$results = DB::table('lector')
			->where('uid', 'LIKE', '%' . $params->uid . '%')
    		->join('lector_tipos_identificador', 'lector.tipo_identificador', '=', 'lector_tipos_identificador.id')
    		->take(25)
    		->get(array(
    			'lector.id as id',
    			'uid',
    			'lector.nombre AS nombre',
    			'lector.limite_diario',
    			'lector.limite_semanal',
    			'tipo_identificador',
    			'lector_tipos_identificador.nombre AS nombre_identificador'));
    		
		
		foreach( $results as $item )
		{
			$lector = (object) array(
				'id' => intval($item->id),
				'uid' => $item->uid,
				'name' => $item->nombre,
				'limite_diario' => intval($item->limite_diario),
				'limite_semanal' => intval($ite->limite_semanal),
				'type' => $item->tipo_identificador,
				'type_name' => $item->nombre_identificador);
			$response->lector_list[] = $lector;
		}

		return Response::json($response);
	}

	/**
	 * Buscar lectores
	 * Devuelve una lista de lectores
	 */
	public function reset_pass() {
		if( Auth::guest() )
		{
			header("HTTP/1.1 401 Unauthorized action");
			echo 'Unauthorized action';
			exit;
		}

		try
		{
			$data = file_get_contents("php://input");
			$params = json_decode($data); // lector

			if( empty($params) )
			{
				throw new Exception('No se enviaron parámetros.');
			}

			$lector = Lector::find($params->id);

			if( empty($lector) )
			{
				throw new Exception('No se encontró al lector indicado.');
			}

			$lector->pass = rand(1000, 9999);
			$lector->save();

			$response = (object) array('message' => "Nueva contraseña generada: {$lector->pass}");

			return Response::json($response);
		}
		catch(Exception $ex)
		{
			header("HTTP/1.1 400 Bad Request");
			if( $ex->getCode() == 1 )
			{
				echo $e->getMessage();
			}
			else {
				echo 'Ocurrió un error. Reinicie la página y vuelva a intentarlo.';
			}
			exit;
		}
	}

	/**
	 * Create user
	 * Crete nuevo lector
	 */
	public function create_lector() {
		if( Auth::guest() )
		{
			header("HTTP/1.1 401 Unauthorized action");
			echo 'Unauthorized action';
			exit;
		}

		try
		{
			$data = file_get_contents("php://input");
			$params = json_decode($data); // lector
			Log::debug('Info lector itinerante (' . print_r($params, TRUE) . ').');

			if( empty($params) )
			{
				throw new Exception('No se enviaron parámetros.');
			}
			if( empty($params->username) )
			{
				throw new Exception('El campo Username es obligatorio.', 1);
			}
			if( empty($params->nombre) )
			{
				throw new Exception('El campo Nombre es obligatorio.', 1);
			}
			if( !is_numeric($params->limite_diario) || $params->limite_diario < 0 )
			{
				throw new Exception('El campo Limite diario es obligatorio y debe ser un número entero.', 1);
			}
                        if( empty($params->apellidos) )
                        {
                                throw new Exception('El campo Apellidos es obligatorio.', 1);
                        } 
			if( empty($params->dni) )
                        {
                                throw new Exception('El campo DNI/Pasaporte es obligatorio.', 1);
                        }
			if (!empty($params->correo))
			{
				//$email = test_input($params->correo);
				if (!filter_var($params->correo, FILTER_VALIDATE_EMAIL)) {
					throw new Exception('El correo electrónico no es correcto.', 1);
				}
			}
/*			if (!empty($params->telefono))
			{
				$pattern = '/^\\+?(34[-. ]?)?\\(?(([689]{1})(([0-9]{2})\\)?[-. ]?|([0-9]{1})\\)?[-. ]?([0-9]{1}))|70\\)?[-. ]?([0-9]{1}))([0-9]{2})[-. ]?([0-9]{1})[-. ]?([0-9]{1})[-. ]?([0-9]{2})$/';
				if (!(bool)preg_match($pattern, $params->telefono)) {
					throw new Exception('Telefono con formato incorrecto.', 1);
				}
			}
*/
                        Log::debug('Creamos el nuevo lector');
		        Log::debug(print_r($params, TRUE));

			$lector = new Lector();
			$lector->username = $params->username;
			$lector->origen = 1;
			$lector->nombre = $params->nombre;
			$lector->limite_diario = $params->limite_diario;
			$lector->limite_semanal = $params->limite_semanal;
			$lector->apellidos = $params->apellidos;
			$lector->correo = $params->correo;
			$lector->dni = $params->dni;
			$lector->notas = $params->notas;
			$lector->telefono = $params->telefono;
			$lector->fechaAlta = date("Y-m-d H:i:s");
			$lector->acumuladoDiario = 0;
			$lector->acumuladoSemanal = 0;
			$lector->save();

			
			$response = (object) array(
				'state' => 'ok',
				'message' => "Usario creado correctamente. A continuación debe generar una contraseña.",
				'lector_id' => $lector->id
			);
			Log::debug('Creado response con lector_id (' . $lector->id . ').');
			return Response::json($response);
		}
		catch(Exception $ex)
		{
			Log::error($ex);

			header("HTTP/1.1 400 Bad Request");
			if( $ex->getCode() == 1 )
			{
				echo $ex->getMessage();
			}
			elseif( $ex->getCode() == 23000 )
			{
				echo "<h4>No se puede crear el lector</h4><p>El Nombre de usuario introducido ya existe en la base de datos.</p>";
			}
			else {
				echo 'Ocurrió un error. Reinicie la página y vuelva a intentarlo. ';
			}
			exit;
		}
	}

	/**
	 * Update lector
	 * Actualizar varios campos del lector
	 */
	public function update_lector() {
		if( Auth::guest() )
		{
			header("HTTP/1.1 401 Unauthorized action");
			echo 'Unauthorized action';
			exit;
		}

		try
		{
			Log::info('update_lector');

			$data = file_get_contents("php://input");
			$params = json_decode($data); // lector

			Log::debug(print_r($params, TRUE));

			if( empty($params) )
			{
				throw new Exception('No se enviaron parámetros.');
			}
			if( empty($params->nombre) )
			{
				throw new Exception('El campo Nombre es obligatorio.', 1);
			}
			if( !is_numeric($params->limite_diario) || $params->limite_diario < 0 )
			{
				throw new Exception('El campo Limite diario es obligatorio y debe ser un número entero.', 1);
			}
			if( empty($params->apellidos) )
                        {
                                throw new Exception('El campo Apellidos es obligatorio.', 1);
                        }
                        if( empty($params->dni) )
                        {
                                throw new Exception('El campo DNI/Pasaporte es obligatorio.', 1);
                        }

			$lector = Lector::find($params->id);
			$lector->nombre = $params->nombre;
			$lector->limite_diario = $params->limite_diario;
			$lector->limite_semanal = $params->limite_semanal;
			$lector->apellidos = $params->apellidos;
                        $lector->correo = $params->correo;
                        $lector->dni = $params->dni;
                        $lector->notas = $params->notas;
                        $lector->telefono = $params->telefono;
			$lector->save();

			$response = (object) array(
				'state' => 'ok',
				'message' => "Usario actualizado correctamente.",
			);

			return Response::json($response);
		}
		catch(Exception $ex)
		{
			Log::error($ex);
			//Log::info("aqui (" .  $ex->getCode() . ").");

			header("HTTP/1.1 400 Bad Request");
                        /*if( strpos($ex->getMessage(), "Duplicate entry") )
                                echo 'El Dni/Pasaporte debe ser unico. ';
                        }
                        exit;*/

			if( $ex->getCode() == 1 )
			{
				echo $ex->getMessage();
			}
			else {
				if (strpos($ex->getMessage(), 'Duplicate') > 0) {
					echo 'El Dni/Pasaporte debe ser unico.';
				}
				else
				{
					echo 'Ocurrió un error. Reinicie la página y vuelva a intentarlo. ';
				}
			}
			exit;
		}
	}

	function lector_info_init()
	{
		if( Auth::guest() )
		{
			header("HTTP/1.1 401 Unauthorized action");
			echo 'Unauthorized action';
			exit;
		}

        $terminal = Estado::where('est_terminal', '=', $this->terminal)
            ->first();

       	// Si no existe terminal o está bloqueada
        if( !isset($terminal) || $terminal->est_status != 1 )
        {
			header("HTTP/1.1 401 Unauthorized action");
			echo 'Unauthorized action';
			exit;
        }

        $lector = Lector::where('terminal', '=', $this->terminal)
            ->first();
        $terminal = Estado::where('est_terminal', '=', $this->terminal)
            ->first();

//        Log::error('terminal: ' . print_r($terminal, TRUE));

        // Identificador de biblioteca
        $biblioteca_id = substr($terminal->est_terminal, 1, 3);

//        Log::error('biblioteca: ' . print_r($biblioteca_id, TRUE));

        $biblioteca = Biblioteca::where('bib_id', '=', $biblioteca_id)
            ->first();

//        Log::error('biblioteca: ' . print_r($biblioteca, TRUE));

        $response = (object) array(
        	'lector' => NULL,
        	'terminal' => (object) array(
        		'id' => $terminal->est_terminal,
        		'timetolive' => $terminal->est_timetolive,
        	),
        	'biblioteca' => (object) array(
        		'nombre' => $biblioteca->bib_name
        	)
        );

        if( isset($lector) )
        {
        	$response->lector = (object) array(
        		'username' => $lector->username,
        		'nombre' => $lector->nombre,
        		'limite_semanal' => $lector->limite_semanal,
        		'limite_diario' => $lector->limite_diario
        	);
        }

		return Response::json($response);
	}

/*	public function comprobar() {
		echo "<pre>Comenzar\n\n";

		$params = array('id' => 'asuarez@opencanarias.es', 'pass' => 'pepe');

		$client = new GuzzleHttp\Client();
		$res = $client->post('https://sigb.biblioteca.es/auth/doauth', $params);
		echo $res->getStatusCode();
		// "200"
		echo $res->getHeader('content-type');
		// 'application/json; charset=utf8'
		echo $res->getBody();
		// {"type":"User"...'


		echo "\nEnd</pre>";
	}
*/
}
