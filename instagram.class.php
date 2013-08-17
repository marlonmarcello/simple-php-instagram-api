<?php

/**
 * Instagram-API-PHP : Integração simples com a API do Instagram V1
 * 
 * PHP version 5.3.10
 * 
 * @category API Integration
 * @package  Instagram-API-PHP
 * @author   Marlon Ugocioni Marcello <marlon.marcello@gmail.com>
 * @license  http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link     não tem ainda
 */
class Instagram
{   
    private $access_token = NULL;
    private $client_id; //: your client id
	private $client_secret; //: your client secret
	private $grant_type = 'authorization_code'; //: authorization_code is currently the only supported value
	private $redirect_uri; //: the redirect_uri you used in the authorization request. Note: this has to be the same value as in the authorization request.
    private $scope; // basic - to read any and all data related to a user (e.g. following/followed-by lists, photos, etc.) (granted by default)
                    // comments - to create or delete comments on a user’s behalf
                    // relationships - to follow and unfollow users on a user’s behalf
                    // likes - to like and unlike items on a user’s behalf
    private $curl;
    private $curlTipo;
    private $curlUrl;

    private $user;

    /**
     * Cria o objeto de acesso a API, é necessário enviar as configurações:
     * client_id, client_secret, redirect_uri
     * Para obter essas informações : http://instagram.com/developer/
     * 
     * @param array $settings
     */
    public function __construct(array $settings)
    {
        if (!in_array('curl', get_loaded_extensions())) 
        {
            throw new Exception('Você precisa instalar cURL, see: http://curl.haxx.se/docs/install.html');
        }
        
        if (!isset($settings['client_id'])
            || !isset($settings['client_secret'])
            || !isset($settings['redirect_uri']))
        {
            throw new Exception('Passe todos os parâmetros corretamente');
        }

        $this->client_id = trim($settings['client_id']);
        $this->client_secret = trim($settings['client_secret']);
        $this->redirect_uri = trim($settings['redirect_uri']);

        if(isset($settings['access_token']) && strlen($settings['access_token']) > 0){
            $this->access_token = $settings['access_token'];
        }

        if(isset($settings['scope']) && count($settings['scope']) > 0){
            $this->scope = implode('+', $settings['scope']);
        }
    }    


    /**
     * Checka se o usuário esta logado e com AccessToken
     * @return boolean
     */
    public function checkAuthentication(){
        if(session_id() == '') {
            session_start();
        }
        
        if(strlen($this->access_token) > 0){
            return true;
        }else{
            if (isset($_SESSION['InstagramAccessToken']) && !empty($_SESSION['InstagramAccessToken'])) {
                return true;
            }
        }
    }

    /**
     * Navega até a página de autorização do Instagram, se ja estiver autenticado retorna para o $redirect_uri
     */
    public function openInstagramAuth($pop = false) {
        $url = 'https://api.instagram.com/oauth/authorize/?client_id='.$this->client_id.'&response_type=code';
        if(strlen($this->scope) > 0)
            $url .= '&scope='.$this->scope;
        $url .= '&redirect_uri='.$this->redirect_uri;

        if(!$pop){            
            header("Location: $url");
            exit(1);
        }else
            return $url;
    }    

    /**
     * Inicia o cURL para começar o chamado   
     * @param string GET ou POST
     */
    public function initRequest($tipo){       

        if(gettype($this->curl) == 'resource') curl_close($this->curl);

        $this->curl = curl_init();
        curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, 1);

        if(strlen($tipo) > 0 && $tipo == 'POST'){
            curl_setopt($this->curl, CURLOPT_POST, 1);
            $this->curlTipo = $tipo;
        }else            
            $this->curlTipo = 'GET';

    }

    /**
     * Cria a URL do chamado
     * @param string SEM '/'
     */
    public function requestUrl($url, $own = false) {
        if(session_id() == '') {
            session_start();
        }

        if(strlen($url) > 0){
            if($own)
                $this->curlUrl = $url;
            else{
                $this->curlUrl = 'https://api.instagram.com/v1/' . $url . '?access_token=';
                $this->curlUrl .= (strlen($this->access_token) > 0) ? $this->access_token : $_SESSION['InstagramAccessToken'];
            }                
        }else
            throw new Exception('URL não informada');
    }


    /**
     * Passa os parametros da url
     * @param array
     */
    public function requestParamns($parametros) {
        
        if(count($parametros) > 0){
            if($this->curlTipo == 'POST'){
                curl_setopt($this->curl, CURLOPT_POSTFIELDS, $parametros);
            }else{
                $i = 0;
                $this->curlUrl .= '&';
                foreach ($parametros as $campo => $val) {
                    $this->curlUrl .= $campo . '=' . $val;
                    if($i < count($parametros))
                        $this->curlUrl .= '&';
                }
            }
        }else
            throw new Exception('PARÂMETROS em branco'); 
    }

   

    /**
     * Requisita a chamada na API
     * 
     * @param boolean se TRUE retorna como json_decode
     * 
     */
    public function performRequest($return = true) {        

        curl_setopt($this->curl, CURLOPT_URL, $this->curlUrl);
        $result = curl_exec($this->curl);
        curl_close($this->curl);        

        if($return)
            return json_decode($result);
        else
            return $result;
    }



    /**
     * Requisita o Access Token para a API do Instagram
     * @param string Codigo que retorna do instagram no momento da autenticação
     * @return Access Token
     */
    public function getAccessToken($code = '') { 
        if(strlen($code) > 0){
            $this->initRequest('POST');
            $this->requestUrl('https://api.instagram.com/oauth/access_token', true);
            $this->requestParamns(array(
                'client_id' => $this->client_id, 
                'client_secret' => $this->client_secret, 
                'grant_type' => $this->grant_type, 
                'redirect_uri' => $this->redirect_uri, 
                'code' => $code)
            );
            // Retorna o Access Token e Salva na sessão            
            $result = $this->performRequest();                     
            var_dump($result);
            $accessToken = $result->access_token;
            $this->saveAccessToken($accessToken);


            // Salva os dados do usuário numa sessão;
            if(session_id() == '')
                session_start();

            $_SESSION['insta_user'] = $result->user;            
        }else{
            if(strlen($this->access_token) > 0)
                $accessToken = $this->access_token;
            else{
                if(session_id() == '') {
                    session_start();
                }

                $accessToken = $_SESSION['InstagramAccessToken'];
            }
        }        
        
        return $accessToken;
    }

    /**
     * Salva o AccessToken em uma session para poder utilizar durante o código
     * @return string
     */
    public function saveAccessToken($accessToken) {
        session_start();
        if(strlen($accessToken) > 0)
            $_SESSION['InstagramAccessToken'] = $accessToken;
        else
            throw new Exception('Access Token em branco.');
    }

    /**
     * Checka se o usuário esta logado e com AccessToken
     * @return boolean
     */
    public function clearAccessToken(){
        if(session_id() == '') {
            session_start();
        }
        
        if (isset($_SESSION['InstagramAccessToken']) && !empty($_SESSION['InstagramAccessToken'])) {
            $_SESSION['InstagramAccessToken'] = NULL;
        }
    }


    /**
     * Pega o ID do nome enviado
     * @return string
     */
    public function getId($nome) {
        $this->initRequest('GET');
        $this->requestUrl('users/search');        
        $this->requestParamns(array('q' => $nome));

        $result = $this->performRequest();        
        return $result->data[0]->id;
    }

}
