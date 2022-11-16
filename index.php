<?php
ob_start();
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE');
header('Access-Control-Allow-Credentials: true');

define('SITE_URL', site_url());
require_once(ABSPATH . 'wp-admin/includes/user.php');
require_once(ABSPATH . 'wp-admin/includes/image.php');
require_once(ABSPATH . 'wp-admin/includes/file.php');
require_once(ABSPATH . 'wp-admin/includes/media.php');
define('ADMIN_EMAIL', 'admin@knoxweb.com');
/*
Plugin Name:API
Description:gfcgv
Version:1.0.0
Author:Ajay
*/

use Firebase\JWT\JWT;
use \Firebase\JWT\Key;

class REST_APIS extends WP_REST_Controller
{
  private $api_namespace;
  private $api_version;
  public function __construct()
  {
    $this->api_namespace = 'api/v';
    $this->api_version = '1';
    $this->required_capability = 'read';
    $this->init();
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
  private function isValidToken()
  {
    $this->user_id  = $this->getUserIdByToken($this->user_token);
  }

  public function register_routes()
  {
    $namespace = $this->api_namespace . $this->api_version;
    $privateItems = array('getUserProfile', 'updateUserProfile', 'getUserProfileData'); //Api Name 
    $publicItems  = array('register', 'aboutUs', 'changePassword');
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

  public function isUserExists($user)
  {
    global $wpdb;
    $count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $wpdb->users WHERE ID = %d", $user));
    if ($count == 1) {
      return true;
    } else {
      return false;
    }
  }

  public function getUserIdByToken($token)
  {

    $decoded_array = array();
    $user_id = 0;
    if ($token) {
      try {
        $decoded = JWT::decode($token, new Key(JWT_AUTH_SECRET_KEY, apply_filters('jwt_auth_algorithm', 'HS256')));

        $decoded_array = (array)$decoded;
        if (count($decoded_array) > 0) {
          $user_id = $decoded_array['data']
            ->user->id;
        }

        if ($this->isUserExists($user_id)) {
          return $user_id;
        } else {
          return false;
        }
      } catch (\Exception $e) { // Also tried JwtException
        return false;
      }
    }
  }

  public function jwt_auth($data, $user)
  {
    unset($data['user_nicename']);
    unset($data['user_display_name']);
    $site_url = site_url();
    //$result = $this->getProfile($user->ID);
    $tutorial = get_user_meta($user->ID, 'tutorial', true);

    $result['token'] =  $data['token'];
    return $this->successResponse('User Logged in successfully', $result);
  }

  // -------------------------------register--------------------------------------

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
      $user_id = wp_create_user($first_name, $password, $email);
      update_user_meta($user_id, 'first_name', $first_name);
      update_user_meta($user_id, 'last_name', $last_name);
      if (!empty($user_id)) {
        return $this->successResponse('User registration successfull.');
      } else {
        return $this->errorResponse('Please try again.');
      }
    }
  }

  // ---------------------------------------------aboutUs------------------------------

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
      $results['post_content'] = stripslashes(strip_tags($post->post_content));
      $data[] = $results;
    }
    wp_reset_postdata();
    if (!empty($data)) {
      return $this->successResponse('', $data);
    } else {
      return $this->errorResponse('No record found');
    }
  }

  // private function isValidToken()
  // {
  //   $this->user_id  = $this->updateUserProfile($this->user_token);
  // }

  // -----------------------------------updateUserProfile---------------------------------

  public function updateUserProfile($request)
  {
    global $wpdb;
    $param = $request->get_params();
    $this->isValidToken();
    $user_id = !empty($this->user_id) ? $this->user_id : $param['user_id'];
    $first_name = $param['first_name'];
    $last_name = $param['last_name'];
    $dob = $param['dob'];
    $weight = $param['weight'];
    $calorie_requirement = $param['calorie_requirement'];
    $water_requirement = $param['water_requirement'];
    $caregiver = $param['caregiver'];
    $profile_pic = $param['profile_pic'];

    if (!empty($user_id)) {
      update_user_meta($user_id, 'first_name', $first_name);
      update_user_meta($user_id, 'last_name', $last_name);
      update_user_meta($user_id, 'dob', $dob);
      update_user_meta($user_id, 'weight', $weight);
      update_user_meta($user_id, 'caregiver', $caregiver);
      update_user_meta($user_id, 'image', $profile_pic);
      return $this->successResponse('record updated successfully.');
    } else {
      return $this->errorResponse('No record found.');
    }
  }

  //------------------------------changePassword---------------------------------

  public function changePassword($request)
  {

    $param = $request->get_params();
    $user_data = get_user_by('email', trim($param['user_email']));
    $user_id = $user_data->ID;

    $new_password = $param['new_password'];
    $con_password = $param['con_password'];

    $user = get_userdata($user_id);
    if (!empty($user_id)) {
      return $this->errorResponse('Please enter the valid token.');
    } else if ($new_password != $con_password) {
      return $this->errorResponse('Please enter same Password.');
    } else {

      $udata['ID'] = $user->data->ID;
      $udata['user_pass'] = $new_password;
      $uid = wp_update_user($udata);

      if ($uid) {
        return $this->successResponse('Password changed successfully');
      } else {
        return $this->errorResponse('Failed to changed Password');
      }
    }
  }

}

$serverApi = new REST_APIS();
$serverApi->init();
add_filter('jwt_auth_token_before_dispatch', array($serverApi, 'jwt_auth'), 10, 2);
