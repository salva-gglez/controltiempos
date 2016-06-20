<?php

/**
 * Autenticación de usuarios
 */
class AuthController extends BaseController {

    protected $layout = 'layouts.master';
    
    private $terminal = NULL;

    function __construct()
    {
        $name = Config::get('controltiempos.default_cookie_name');

        if( isset($_COOKIE[$name]) )
        {
            $this->terminal = $_COOKIE[$name];
        }
    }

    /**
     * Entrada para configurar el terminal
     * @param terminal Identificador del terminal que llega por get: TXXXYY.
     */
    public function initialize()
    {
        // Si se pasa el parámetro terminal, se sobrescribe la cookie
        if( Input::has('terminal') )
        {
            $terminal = Input::get('terminal');

            if( !empty($terminal) )
            {
                $name = Config::get('controltiempos.default_cookie_name');
                $time = Config::get('controltiempos.default_cookie_time');

                if( setcookie($name, $terminal, time() + $time) === TRUE )
                {
                    $this->terminal = strtoupper($terminal);
                    //log:error("Incializado terminal {this->terminal}.");
                }
                Log::error("Se crea/sobrescribe la cookie: {$name}, {$terminal}");
            }
        }

        //$terminal = Estado::find($this->terminal);

        //if( $terminal)

        return Redirect::to('/');
    }

    /**
     * Muestra el formulario para login de administrador.
     * No se tiene en cuenta el terminal.
     */
    public function showAdminLogin()
    {
        if (Auth::check())
        {
            return Redirect::to('/');
        }
        return View::make('admin_login');
    }

    /**
     * Valida los datos del usuario administrador.
     */
    public function postAdminLogin()
    {
        $user = User::where('usu_name', '=', Input::get('username'))
            ->where('tipo', '=', 1)
            ->first();

        if( isset($user) )
        {
//            if($user->Password == md5(Input::get('password'))) { // If their password is still MD5
            if($user->usu_pass == Input::get('password')) {
                Auth::login($user);
                return Redirect::to('/');
            }
        }

        return Redirect::to('login')
            ->with('mensaje_error', 'Tus datos son incorrectos')
            ->withInput();
    }    

    /**
     * Muestra el formulario para login.
     */
    public function showLogin()
    {
        if (Auth::check())
        {
            return Redirect::to('/');
        }

        /**
         * Si la terminal está activa, buscar el usuario y mostrarlo
         */
        $terminal = Estado::where('est_terminal', '=', $this->terminal)
            ->first();

        // Si no existe el terminal en la base de datos, mostrar error
        if( !isset($terminal) )
        {
            return View::make('not_initialized');
        }

        /**
         * Si el terminal está activo, vamos a la ventana de info
         */
        if( $terminal->est_status == 1 ) // Activa
        {
            // Si el terminal está activo, mostramos la ventana de info
            Auth::login( $this->get_user_lector() );

            return Redirect::to('/');
        }

        // Identificador de biblioteca
        $biblioteca_id = substr($terminal->est_terminal, 1, 3);

        $biblioteca = Biblioteca::where('bib_id', '=', $biblioteca_id)
            ->first();

        $estado = Estados::where('id', '=', $terminal->est_status)
	    ->first();
        $bloqueado = 'false';
        if ( isset($estado)) {
		Log::debug("Estado del terminal (" . $estado->estado . ").");
  		$estadoTerminal = $estado->estado;
		if ( $estadoTerminal === "Bloqueado" )
			$bloqueado = 'true';
	} else {
		$estadoTerminal = 'Desconocido';
	}
	
	$data = array(
            'biblioteca' => (object) array(
                'nombre' => $biblioteca->bib_name
            ),
            'terminal' => (object) array(
                'id' => $terminal->est_terminal,
		'status' => $estadoTerminal,
                'disabled' => $bloqueado),
        );
        return View::make('login', $data);
    }

    /**
     * Valida los datos del usuario.
     */
    public function postLogin()
    {
        $username = Input::get('username');

        if( empty($username) )
        {
            return Redirect::to('login')
                ->with('mensaje_error', 'Tus datos son incorrectos')
                ->withInput();
        }

        $terminal = Estado::where('est_terminal', '=', $this->terminal)
            ->first();

        // Primera opción: autenticación con base de datos local

        // Intentos de validación
        $attempts = Session::get('attempts', array());

        if( isset($attempts[$username]) )
        {
            $data = $attempts[$username];
            $data->count++;

            $max =  Config::get('controltiempos.login_attempts_max');
            $minutos =  Config::get('controltiempos.login_attempts_minutes');
            $limite = $data->timestamp + ($minutos * 60);

            if( time() > $limite )
            {
                // Ya pasó el tiempo, reset
                $data->timestamp = time();
                $data->count = 0;
            }

            if( time() < $limite )
            {
                if( $data->count >= $max ) 
                {
                    return Redirect::to('login')
                        ->with('mensaje_error', "Usuario bloqueado durante los próximos {$minutos} minutos.")
                        ->withInput();
                }
            }
        }
        else
        {
            // Primer acceso
            $data = (object) array('timestamp' => time(), 'count' => 0);
        }

        $attempts[$username] = $data;

        Session::put('attempts', $attempts);


        $lector = Lector::where('username', '=', $username)
            ->where('origen', '=', 1) // Itinerante
            ->first();
 
        Log::info('aaaa' . $lector);
        if( isset($lector) )
        {
            if( $lector->pass == Input::get('password') )
            {
		// Comprobamos que el usuario tenga tiempo disponible 
               	Log::debug('Comprobacion limites de tiempo semanal (acumulado=' .$lector->acumuladoSemanal . ') y limite en (' . $lector->limite_semanal . ').');
	        if ( $lector->acumuladoSemanal > $lector->limite_semanal ) {
			Log::debug('Limite semanal superado...');
	                return Redirect::to('login')
        	                ->with('mensaje_error', 'Limite de tiempo semanal excedido.')
                	        ->withInput();
	        }
                // Ahora el tiempo diario
		Log::debug('Comprobacion limites de tiempo diario (acumulado=' . $lector->acumuladoDiario . ') y limite en (' . $lector->limite_diario . ').');
		if ( $lector->acumuladoDiario > $lector->limite_diario ) {
                        Log::debug('Limite diario superado...');
                        return Redirect::to('login')
                                ->with('mensaje_error', 'Limite de tiempo diario excedido.')
                                ->withInput();
                } 

		//Comprobamos que el usuario no este conectado en otro terminal
		if (!is_null($lector->terminal)) {
                	return Redirect::to('login')
                	->with('mensaje_error', 'El usuario ya esta conectado en otro terminal.')
                	->withInput();
		}

                $lector->terminal = $this->terminal;
                $lector->ultimo_login = date("Y-m-d H:i:s");
                $lector->save();

                // Quitar el terminal al resto de lectores que la tuvieron anteriormente
                Lector::where('terminal', '=', $this->terminal)
                    ->where('id', '<>', $lector->id)
                    ->update(array('terminal' => NULL));

                $terminal->est_status = 1;
                $terminal->est_timetolive = ($lector->limite_diario - $lector->acumuladoDiario);
                $terminal->save();

                // Cargar un usuario tipo lector
                Auth::login( $this->get_user_lector() );

		// Registrar el acceso
                $registro = new Registro();
                $registro->reg_terminal = $this->terminal;
                $registro->reg_hora = date("Y-m-d H:i:s");
                $registro->reg_lector = $lector->id;
                $registro->reg_resultadoConexion = 0;
                $registro->reg_tiempoUsado = 0;
                $registro->save();

                // Quitar contador
                unset($attempts[$username]);
                Session::put('attempts', $attempts);

                return Redirect::to('/');
            }

            // Contraseña no coincide
            return Redirect::to('login')
                ->with('mensaje_error', 'Tus datos son incorrectos')
                ->withInput();
        }

        // No existe el usuario en la base de datos local, buscar en absys

        try
        {
	    	Log::info('Validando usuario (' . Input::get('username') . ') y pass (' . Input::get('password') . ').');
            $data = $this->absys_login(Input::get('username'), Input::get('password'));
            Log::debug("Resultado status (" . $data->status . ").");

            $resultadoAbsys = "";
            switch (intval($data->status)) {
            	case 0: Log::debug("Lector validado con absys: (" . print_r($lector,TRUE) . ").");
            			$resultadoAbsys = "Lector validado correctamente en Absys.";
            			break;
            	case 1: Log::debug("Correo no registrado en absys: (" . print_r($lector,TRUE) . ").");
            			$resultadoAbsys = "Correo electrónico no registrado en Absys.";
            			break;
            	case 2: Log::debug("Mas de un registro de lector con este correo en absys: (" . print_r($lector,TRUE) . ").");
            			$resultadoAbsys = "Mas de un lector registrado con este correo electrónico en Absys.";
            			break;
            	case 3: Log::debug("Contraseña incorrecta: (" . print_r($lector,TRUE) . ").");
            			$resultadoAbsys = "La contraseña no es valida.";
            			break;
            	case 4:	Log::debug("Servicio absys no disponible: (" . print_r($lector,TRUE) . ").");
            			$resultadoAbsys = "Servicio absys no disponible.";
            			break;
            	default: Log::debug("Mensaje de error de Absys no registrado.");
            			 $resultadoAbsys = "Mensaje de error de Absys no registrado.";
            }
            // Status a 0 indica que usuario y clave son correctos
            if( intval($data->status) == 0 )
            {
                DB::beginTransaction();

                $lector = Lector::where('username', '=', Input::get('username'))->first();
                //Buscamos el lector por si ya esta creado
                Log::debug("(Autenticando) Lector validado con absys: (" . print_r($lector,TRUE) . ").");

                if( isset($lector) )
                {
                    Log::debug("Datos del lector: (" . print_r($lector,TRUE) . ") y terminal (" . $this->terminal . ").");
                	if (!is_null($lector->terminal)) {
                        	return Redirect::to('login')
	                        ->with('mensaje_error', 'El usuario ya esta conectado en otro terminal.')
        	                ->withInput();
                	}

 		    		Log::debug("Actualizando lector.");
                    // Actualizar estado
                    $lector->origen = 2; // Absys
                    $lector->terminal = $this->terminal;
                    $lector->ultimo_login = date("Y-m-d H:i:s");
                }
                else
                {
                    Log::debug("Creando lector nuevo.");
                    // Creamos el lector correspondinete en nuestra base de datos
                    $lector = new Lector();
                    $lector->username = $data->mail;
                    $lector->nombre = $data->name;
                    $lector->origen = 2; // Absys
                    $lector->terminal = $this->terminal;
                    $lector->ultimo_login = date("Y-m-d H:i:s");
                    $lector->limite_diario = Config::get('controltiempos.limite_diario');
                    $lector->limite_semanal = Config::get('controltiempos.limite_semanal');
	            	$lector->notas = print_r($data, TRUE);
		    		$lector->acumuladoDiario = 0;
		    		$lector->acumuladoSemanal = 0;
                }

                $lector->save();

                // El terminal al que accede el usuario no debe estar asignado a ningun otro lector
                Lector::where('terminal', '=', $this->terminal)
                    ->where('id', '<>', $lector->id)
                    ->update(array('terminal' => NULL));

                $terminal->est_status = 1;
                $terminal->est_timetolive = $lector->limite_diario;
                $terminal->save();

                Auth::login( $this->get_user_lector() );

				// Registrar el acceso
                $registro = new Registro();
                $registro->reg_terminal = $this->terminal;
                $registro->reg_hora = date("Y-m-d H:i:s");
                $registro->reg_lector = $username;
                $registro->reg_resultadoConexion = 0;
                $registro->reg_tiempoUsado = 0;
                $registro->save();

                // Quitar contador
                unset($attempts[$username]);
                Session::put('attempts', $attempts);

                DB::commit();

                return Redirect::to('/');
            }

            return Redirect::to('login')
                    ->with('mensaje_error', 'Tus datos son incorrectos. (' . $resultadoAbsys . ')')
                    ->withInput();
        }
        catch(Exception $ex)
        {
            DB::rollBack();

            Log::error($ex->getMessage());

            return Redirect::to('login')
                ->with('mensaje_error', 'Ocurrió un error al conectar con servicio de autenticación.')
                ->withInput();
        }
    }

    /**
     * Devuelve el usuario lector.
     * Si no existe, lo crea.
     */
    private function get_user_lector()
    {
        // Cargar un usuario tipo lector
        $user = User::where('tipo', '=', 2)->first();

        if( !isset($user) )
        {
            // No existe, se crea
            $user = new User();
            $user->usu_name = 'Lector';
            $user->tipo = 2; // lector
            $user->save();
        }
        return $user;
    }

    /**
     * Validación contra servicio de absys
     * @see app/config/controltiempos.php Para configuración de la URL
     */
    private function absys_login($username, $password)
    {
        try
        {
            $url = Config::get('controltiempos.absys_url');
            $url = sprintf($url, $username, $password);
	    Log::debug('Url: (' . $url . ')');

            // create curl resource 
            $ch = curl_init(); 

            // set url 
            curl_setopt($ch, CURLOPT_URL, $url); 

            //return the transfer as a string 
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

            // Quitar la verificación SSL
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            // Proxy
            $proxy = Config::get('controltiempos.curlopt_proxy');
            if( !empty($proxy) )
            {
                curl_setopt($ch, CURLOPT_PROXY, $proxy);
		Log::debug('Colocando proxy: (' . $proxy . ').');
            }

            // $output contains the output string 
            $output = curl_exec($ch);
	    Log::debug('Salida curl: (' . $output . ').');

            if( $output === FALSE )
            {
                throw new Exception('Curl error: ' . curl_error($ch));
            }

            // close curl resource to free up system resources 
            curl_close($ch);   

            Log::debug("Decodificando salida curl.");

            $data = JSON_decode($output);
            Log::debug("Decodificado (" . print_r($data, TRUE) . ").");

            if( !is_object($data) ) {
                throw new Exception('La respuesta del servidor absys no es correcta.');
            }

            Log::info("Resultado: (" . print_r($data, TRUE) . ").");
            return $data;
        }
        catch(Exception $ex)
        {
            Log::error('absys_login: error');
            Log::error('absys_login: ' . $ex->getMessage());

            throw new Exception('Error de conexión al servicio absys.');
        }
    }

    /**
     * Muestra el formulario de login mostrando un mensaje de que cerró sesión.
     */
    public function logOut()
    {
        if( Auth::user()->tipo == 2 )
        {
            $terminal = Estado::where('est_terminal', '=', $this->terminal)
                ->first();

            if( isset($terminal) && $terminal->est_status == 1 )
            {
                try
                {
                    DB::beginTransaction();

                    // Cerrar terminal
                    $terminal->est_status = 0;
                    $terminal->est_timetolive = 0;
                    $terminal->est_logontime = NULL;
                    $terminal->est_alive = NULL;
                    $terminal->save();

                    // Se desvincula el terminal del lector
                    Lector::where('terminal', '=', $this->terminal)
                        ->update(array('terminal' => NULL));

                    DB::commit();
                }
                catch(Exception $ex)
                {
                    DB::rollBack();

                    Log::error('api/logout: error al cerrar el terminal.');
                    Log::error($ex->getMessage());
                }
            }

        }

        Auth::logout();

        return Redirect::to('login')
            ->with('mensaje_error', 'Tu sesión ha sido cerrada.');
    }

}
