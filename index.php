<?php
ob_start();
/*
Plugin Name:API
Description:gfcgv
Version:1.0.0
Author:Ajay
*/

use Firebase\JWT\JWT;
 
class REST_APIS extends WP_REST_Controller{
    private $api_namespace;
    private $api_version;
    public function __construct()
    {
      $this->api_namespace = 'api/v';
      $this->api_version = '1';
      $this->required_capability = 'read';
     // $this->init();
      /*------- Start: Validate Token Section -------*/
      $headers = getallheaders();
      if (isset($headers['Authorization'])) {
        if (preg_match('/Bearer\s(\S+)/', $headers['Authorization'], $matches)) {
          $this->user_token =  $matches[1];
        }
      }
    }
    /*------- End: Validate Token Section -------*/
    private function successResponse($message = '', $data = array(), $total = array())
    {
        $response = array();
        $response['status'] = "success";
        $response['message'] = $message;
        $response['data'] = $data;
        if (!empty($total)) {
        $response['total'] = $total;
        }
        return new WP_REST_Response($response, 200);
    }
    private function errorResponse($message = '', $type = 'ERROR', $statusCode = 200)
    {
        $response = array();
        $response['status'] = "error";
        $response['error_type'] = $type;
        $response['message'] = $message;
        return new WP_REST_Response($response, $statusCode);
    }
    
    public function register_routes()
    {
      $namespace = $this->api_namespace . $this->api_version;
      $privateItems = array('getUserProfile'); //Api Name 
      $publicItems  = array('register','aboutUs');
      foreach ($privateItems as $Item) {
        register_rest_route(
          $namespace,
          '/' . $Item,
          array(
            array(
              'methods' => 'POST',
              'callback' => array($this, $Item),
              'permission_callback' => !empty($this->user_token) ? '__return_true' : '__return_false'
            ),
          )
        );
      }
  
      foreach ($publicItems as $Item) {
        register_rest_route(
          $namespace,
          '/' . $Item,
          array(
            array(
              'methods' => 'POST',
              'callback' => array($this, $Item)
            ),
          )
        );
      }
    }
    public function init()
    {
      add_action('rest_api_init', array($this, 'register_routes'));
      add_action('rest_api_init', function () {
        remove_filter('rest_pre_serve_request', 'rest_send_cors_headers');
        add_filter('rest_pre_serve_request', function ($value) {
          header('Access-Control-Allow-Origin: *');
          header('Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE');
          header('Access-Control-Allow-Credentials: true');
          return $value;
        });
      }, 15);
    }
  
  
    function jwt_auth($data, $user)
    {
      unset($data['user_nicename']);
      unset($data['user_display_name']);
      $site_url = site_url();
      //$result = $this->getProfile($user->ID);
      $tutorial = get_user_meta($user->ID, 'tutorial', true);
  
      $result['token'] =  $data['token'];
      return $this->successResponse('User Logged in successfully', $result);
    }
  

    public function register($request)
    {
      global $wpdb;
      $param = $request->get_params();
      // hospital data
      $first_name = $param['first_name'];
      $last_name = $param['last_name'];
      $email = $param['email'];
      $password = $param['password'];
      if (email_exists($email)) {
        return $this->errorResponse('Email already exists.');
      } else {
        // User Info     
        $user_id = wp_create_user($email, $password, $email);
        update_user_meta($user_id, 'user_name', $first_name);
        update_user_meta($user_id, 'user_name', $last_name);
        if (!empty($user_id)) {
          return $this->successResponse('User registration successfull.');
        } else {
          return $this->errorResponse('Please try again.');
        }
      }
    }

    
  public function aboutUs($request)
  {
    $args = array(
      'p' => $request['id'],
      'post_type' => 'page',
    );
    if (!$post = get_post($request['id'])) {
      return new WP_Error('invalid_id', 'Please define a valid post ID.');
    }

    $query = new WP_Query($args);
    if ($query->have_posts()) {
      $query->the_post();
      $post = get_post(get_the_ID());
      $id = get_the_ID();
      $title = $post->post_title;
      $results['id'] = $id;
      $results['title'] = $title;
      $results['post_content'] = $post->post_content;
      $data[] = $results;
    }
    wp_reset_postdata();
    if (!empty($data)) {
      return $this->successResponse('', $data);
    } else {
      return $this->errorResponse('No record found');
    }
  }

}

$serverApi = new REST_APIS();
$serverApi->init();
add_filter('jwt_auth_token_before_dispatch',array($serverApi,'jwt_auth'),10,2);
