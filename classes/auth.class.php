<?php 
    require_once("connection/connect.php");
    require_once("response.class.php");
    
    class auth extends cls_dbtools{

        //Creamos el método login
        public function login($json){
            $_respuestas = new response;
            $datos = json_decode($json,true); //el true convierte el array en asociativo
            //Checkea que existan los campos user y password
            if(!isset($datos['usuario']) || !isset($datos['password'])){
                //Error ya que no existen
                return (!isset($datos['usuario']) && !isset($datos['password'])) ? $_respuestas->getError('6037') : (!isset($datos['usuario'])) ? $_respuestas->getError('6040') : $_respuestas->getError('6041') ;
            }else{
                $usuario = $datos['usuario'];
                $password = $datos['password'];
                //Todo esto se debe documentar correctamente
                $password = $this->encrypt($password);
                /**
                 * La variable datos se sobreescribe con los datos actuales
                 * del usuario traidos de la DB
                 */
                $datos = $this->obtenerDatosUsuario($usuario);
                if($datos){
                    /**
                     * Si existe el usuario, creamos el token de autenticación
                     * Verificamos que la contraseña sea correcta
                     * Esta contraseña debe estar encriptada
                     * 
                     */
                    if($password == $datos[0]['password']){
                        //Verificamos que el usuario este activo y que sea del tipo WEBSERVICES
                        if($datos[0]['id_status'] == 1 && $datos[0]['user_type'] == 9){
                            /**
                             * creamos el token
                             * debemos verificar que en los datos que trajimos de la db
                             * no esten la IP ni el token, y hacer las verificaciones necesarias
                             */
                            $verificar = $this->insertarToken($datos);
                            //Revisamos si se guardó
                            if($verificar){

                                $result = $_respuestas->response;
                                $result['result'] = array(
                                    "token" => $verificar   
                                );
                                return $result;
                            }else{
                                return $_respuestas->error_500();
                            }
                        }else{
                            return $_respuestas->getError('1023');    
                        }
                    }else{
                        return $_respuestas->error_400("El password es invalido");    
                    }

                }else{
                    //no existe el usuario
                    return $_respuestas->error_400("El usuario $usuario no existe");
                }
            }
        }

        //Esta funcion se esta modificando en base a lo existente en las DB actuales
        private function obtenerDatosUsuario($correo){
            $query = "SELECT users,password,id_status,user_type,ip_remote,api_key,id FROM users WHERE users.users = '$correo'";
            $datos = $this->_SQL_tool($this->SELECT, __METHOD__, $query);
            if(isset($datos[0]['users'])){
                return $datos;
            }else{
                return 0;
            }
        }

        //Método para crear e insertar el token
        private function insertarToken($datos){
            $val = true;
            $token = $this->valueRandom();
            $ipuser = $_SERVER['REMOTE_ADDR'];
            //Hay que cambiar el como se genera la api_key
            /**
             * Esto se cambiará por un update
             * solo se accesará a esta función despues de que la validación confirme
             * que la IP es nueva y no existe un token
             */
            if(!isset($datos[0]['api_key']) or $ipuser != $datos[0]['ip_remote']){
                /**
                 * El error 500 en este momento se da porque hay usuarios que tienen
                 * IP remote, pero no tiene apikey, entonces no entra aqui
                 */
                $iduser = $datos[0]['id'];
                $query = "UPDATE users SET api_key = '$token', ip_remote = '$ipuser' WHERE id = '$iduser'";
                $verificar = $this->_SQL_tool($this->UPDATE, __METHOD__, $query);
                if($verificar >= 1){
                    return $token;
                }else{
                    return 0;
                }
            }else{
                /** 
                 * Y si entra aqui no retorna nada, debido a que no trajo
                 * la api key desde la DB ya que no existe
                */
                return $datos[0]['api_key'];
            }
        }
        
        /**
         * Funcion traida desde el viejo webservices
         * ya que encaja en el campo webservices
         */
        public function valueRandom($length = 12)
        {
            $chr = "0123456789ABCDEFGHIJKML";
            $str = "";
            while (strlen($str) < $length) {
                $str .= substr($chr, mt_rand(0, (strlen($chr))), 1);
            }
            return ($str);
        }

        public function encrypt($string){
            $salt = "1NsT3pD3veL0p3R$";
    
            $password = hash('sha256', $salt.$string);
    
            return $password;
            //Cambiada la encriptacion a la utlizada actualmente por las plataformas
        }  

        private function insertDynamic($data = array(), $table = null)
        {
            if (empty($table) || count($data) == 0) {
                return false;
            }
            $arrFiels       = [];
            $arrValues      = [];
            $SQL_functions  = [
                'NOW()'
            ];
            foreach ($data as $key => $value) {
                $arrFiels[] = '`' . $key . '`';
                if (in_array(strtoupper($value), $SQL_functions)) {
                    $arrValues[] = strtoupper($value);
                } else {
                    $arrValues[] = '\'' . $value . '\'';
                }
            }
            $query = "INSERT INTO $table (" . implode(',', $arrFiels) . ") VALUES (" . implode(',', $arrValues) . ")";
            return $this->_SQL_tool($this->INSERT, __METHOD__,$query);
        }

        public function logsave($operacion,$request,$_response,$prefijo,$procedencia = '1',$token,$id_error,$num_voucher,$num_referencia,$idUser){
            /**
             * Datos que debemos recibir:
             * -fecha
             * -hora
             * -IP
             * -Operación realizada
             * -Datos (facil)
             * -Respuesta obtenida
             * -Prefijo
             * -procedencia ???
             * -apikey
             * -id_error
             * -num_voucher
             * -num_referencia
             * -id_user
             */

            $data   = [
                'fecha'             => 'NOW()',
                'hora'              => 'NOW()',
                'ip'                => $_SERVER['REMOTE_ADDR'],
                'operacion'         => $operacion,
                'datos'             => $request,
                'respuesta'         => $_response,
                'prefijo'           => $prefijo,
                'procedencia'       => $procedencia,
                'apikey'            => $token,
                'id_error'          => $id_error,
                'num_voucher'       => $num_voucher,
                'num_referencia'    => $num_referencia,
                'id_user'           => ($idUser) ? $idUser : 0
            ];
            return $this->insertDynamic($data, 'trans_all_webservice');
        }
    }
?>