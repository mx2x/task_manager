<?php

// echo phpinfo();
// die();

error_reporting(E_ALL);
ini_set('display_errors', 1);


require_once '../include/DbHandler.php';
require_once '../include/PassHash.php';
require '.././libs/Slim/Slim.php';

\Slim\Slim::registerAutoloader();



$app = new \Slim\Slim();

// User id from db - Global Variable
$user_id = NULL;

/**
 * Adding Middle Layer to authenticate every request
 * Checking if the request has valid api key in the 'Authorization' header
 */
function authenticate(\Slim\Route $route) {
    // Getting request headers
    $headers = apache_request_headers();
    $response = array();
    $app = \Slim\Slim::getInstance();

    // Verifying Authorization Header
    if (isset($headers['Authorization'])) {
        $db = new DbHandler();

        // get the api key
        $api_key = $headers['Authorization'];
        // validating api key
        if (!$db->isValidApiKey($api_key)) {
            // api key is not present in users table
            $response["error"] = true;
            $response["message"] = "Access Denied. Invalid Api key";
            echoRespnse(401, $response);
            $app->stop();
        } else {
            global $user_id;
            // get user primary key id
            $user_id = $db->getUserId($api_key);
        }
    } else {
        // api key is missing in header
        $response["error"] = true;
        $response["message"] = "Api key is misssing";
        echoRespnse(400, $response);
        $app->stop();
    }
}

/**
 * ----------- METHODS WITHOUT AUTHENTICATION ---------------------------------
 */
/**
 * User Registration
 * url - /register
 * method - POST
 * params - name, email, password
 */
$app->post('/register', function() use ($app) {

     $headers = request_headers();
    
 
        // if(!$authorized){ //key is false
                // dont return 403 if you request the home page
                // check for required params
            verifyRequiredParams(array('name', 'email', 'password', 'mobile_no'));

            $response = array();

            // reading post params
            $name = $app->request->post('name');
            $email = $app->request->post('email');
            $password = $app->request->post('password');
            $mobile_no = $app->request->post('mobile_no');


            // validating email address
            validateEmail($email);

            $db = new DbHandler();
            $res = $db->createUser($name, $email, $password, $mobile_no);

            if ($res == USER_CREATED_SUCCESSFULLY) {
                $response["error"] = false;
                $response["message"] = "You are successfully registered";
            } else if ($res == USER_CREATE_FAILED) {
                $response["error"] = true;
                $response["message"] = "Oops! An error occurred while registereing";
            } else if ($res == USER_ALREADY_EXISTED) {
                $response["error"] = true;
                $response["message"] = "Sorry, this email already existed";
            }else if ($res == PHONE_ALREADY_EXISTED) {
                $response["error"] = true;
                $response["message"] = "Sorry, this phone no is already registered";
            }
            // echo json response
            echoRespnse(201, $response);
        // }



            
        });








/**
 * User Registration
 * url - /register
 * method - POST
 * params - name, email, password
 */
$app->post('/checkin/:id', function($user_id) use ($app) {

     $headers = request_headers();

        // if(!$authorized){ //key is false
                // dont return 403 if you request the home page
                // check for required params
            verifyRequiredParams(array('checkin_time'));

            $response = array();

            // reading post params
            $checkin_time = $app->request->post('checkin_time');


            // validating email address
            

            $db = new DbHandler();
            $res = $db->createCheckin($user_id, $checkin_time);

            if ($res == USER_CREATED_SUCCESSFULLY) {
                $response["status"] = "success";
                $response["message"] = "checkedin successfully";
            } else if ($res == USER_CREATE_FAILED) {
                $response["status"] = "failure";
                $response["message"] = "Oops! unable to checkin now";
            } else if ($res == USER_ALREADY_EXISTED) {
                $response["status"] = "failure";
                $response["message"] = "Sorry, this email already existed";
            }else if ($res == PHONE_ALREADY_EXISTED) {
                $response["status"] = "failure";
                $response["message"] = "Sorry, this phone no is already registered";
            }
            // echo json response
            echoRespnse(201, $response);
        // }



            
        });


/**
 * User Login
 * url - /login
 * method - POST
 * params - email, password
 */
$app->post('/login', function() use ($app) {
            // check for required params
            verifyRequiredParams(array('email', 'password'));

            // reading post params
            $email = $app->request()->post('email');
            $password = $app->request()->post('password');
            $response = array();

            $db = new DbHandler();
            // check for correct email and password
            if ($db->checkLogin($email, $password)) {
                // get the user by email
                $user = $db->getUserByEmail($email);

                if ($user != NULL) {
                    $response["error"] = false;
                    $temp = array();  
                    $temp['name'] = $user['name'];
                    $temp['email'] = $user['email'];
                    $temp['apiKey'] = $user['api_key'];
                    $temp['createdAt'] = $user['created_at'];
                    $response['data'] = $temp;
                } else {
                    // unknown error occurred
                    $response['error'] = true;
                    $temp = array();  
                    $temp['message'] = "An error occurred. Please try again";
                    $response['data'] = $temp;
                }
            } else {
                // user credentials are wrong
                $response['error'] = true;
                 $temp = array();  
                    $temp['message'] = "Login failed. Incorrect credentials";
                    $response['data'] = $temp;
               
            }

            echoRespnse(200, $response);
        });

/*
 * ------------------------ METHODS WITH AUTHENTICATION ------------------------
 */

/**
 * Listing all tasks of particual user
 * method GET
 * url /tasks          
 */
$app->get('/tasks', 'authenticate', function() {
            global $user_id;
            $response = array();
            $db = new DbHandler();
            
            // fetching all user tasks
            $result = $db->getAllUserTasks($user_id);
            $response["error"] = false;
            $response["tasks"] = array();


            // looping through result and preparing tasks array
            while ($task = $result->fetch_assoc()) {
                $tmp = array();
                $tmp["id"] = $task["id"];
                $tmp["task"] = $task["task"];
                $tmp["status"] = $task["status"];
                $tmp["createdAt"] = $task["created_at"];
                array_push($response["tasks"], $tmp);
            }

            echoRespnse(200, $response);
        });

/**
 * Listing single task of particual user
 * method GET
 * url /tasks/:id
 * Will return 404 if the task doesn't belongs to user
 */
$app->get('/tasks/:id', 'authenticate', function($task_id) {
            global $user_id;
            $response = array();
            $db = new DbHandler();

            // fetch task
            $result = $db->getTask($task_id, $user_id);

            if ($result != NULL) {
                $response["error"] = false;
                $response["id"] = $result["id"];
                $response["task"] = $result["task"];
                $response["status"] = $result["status"];
                $response["createdAt"] = $result["created_at"];
                echoRespnse(200, $response);
            } else {
                $response["error"] = true;
                $response["message"] = "The requested resource doesn't exists";
                echoRespnse(404, $response);
            }
        });

/**
 * Creating new task in db
 * method POST
 * params - name
 * url - /tasks/
 */
$app->post('/tasks', 'authenticate', function() use ($app) {
            // check for required params

            $entityBody = file_get_contents('php://input');
            $res=json_decode($entityBody,true);
          // echoRespnse(200, $res);
          //   die();
            // $data = verifyRequiredParams(array('task'));


 
            $response = array();
            $task = $app->request->post('task');
 //print_r($task);
    //die("asdf");
           

            global $user_id;
            $db = new DbHandler();

            // creating new task
            $task_id = $db->createTask($user_id, $res);

            if ($task_id != NULL) {
                $response["error"] = false;
                $response["message"] = "Task created successfully";
                $response["task_id"] = $task_id;
                echoRespnse(201, $response);
            } else {
                $response["error"] = true;
                $response["message"] = "Failed to create task. Please try again";
                echoRespnse(200, $response);
            }            
        });

/**
 * Updating existing task
 * method PUT
 * params task, status
 * url - /tasks/:id
 */
$app->put('/tasks/:id', 'authenticate', function($task_id) use($app) {
            // check for required params
            verifyRequiredParams(array('task', 'status'));

            global $user_id;            
            $task = $app->request->put('task');
            $status = $app->request->put('status');

            $db = new DbHandler();
            $response = array();

            // updating task
            $result = $db->updateTask($user_id, $task_id, $task, $status);
            if ($result) {
                // task updated successfully
                $response["error"] = false;
                $response["message"] = "Task updated successfully";
            } else {
                // task failed to update
                $response["error"] = true;
                $response["message"] = "Task failed to update. Please try again!";
            }
            echoRespnse(200, $response);
        });

/**
 * Deleting task. Users can delete only their tasks
 * method DELETE
 * url /tasks
 */
$app->delete('/tasks/:id', 'authenticate', function($task_id) use($app) {
            global $user_id;

            $db = new DbHandler();
            $response = array();
            $result = $db->deleteTask($user_id, $task_id);
            if ($result) {
                // task deleted successfully
                $response["error"] = false;
                $response["message"] = "Task deleted succesfully";
            } else {
                // task failed to delete
                $response["error"] = true;
                $response["message"] = "Task failed to delete. Please try again!";
            }
            echoRespnse(200, $response);
        });





/**
 * Verifying required params posted or not
 */
function verifyRequiredParams($required_fields) {


    $error = false;
    $error_fields = "";
    $request_params = array();
    $request_params = $_REQUEST;
    // Handling PUT request params
    if ($_SERVER['REQUEST_METHOD'] == 'PUT') {
        $app = \Slim\Slim::getInstance();
        parse_str($app->request()->getBody(), $request_params);
    }



    foreach ($required_fields as $field) {
        if (!isset($request_params[$field]) || strlen(trim($request_params[$field])) <= 0) {
            $error = true;
            $error_fields .= $field . ', ';
        }
    }



    if ($error) {
        // Required field(s) are missing or empty
        // echo error json and stop the app
        $response = array();
        $app = \Slim\Slim::getInstance();
        $response["error"] = true;
        $response["message"] = 'Required field(s) ' . substr($error_fields, 0, -2) . ' is missing or empty';
        echoRespnse(400, $response);
        $app->stop();
    }
}

/**
 * Validating email address
 */
function validateEmail($email) {
    $app = \Slim\Slim::getInstance();
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response["error"] = true;
        $response["message"] = 'Email address is not valid';
        echoRespnse(400, $response);
        $app->stop();
    }
}

/**
 * Echoing json response to client
 * @param String $status_code Http response code
 * @param Int $response Json response
 */
function echoRespnse($status_code, $response) {
    $app = \Slim\Slim::getInstance();
    // Http response code
    $app->status($status_code);

    // setting response content type to json
    $app->contentType('application/json');

    echo json_encode($response);
}






function request_headers() {
        $arh = array();
        $rx_http = '/\AHTTP_/';
        foreach($_SERVER as $key => $val) {
                if( preg_match($rx_http, $key) ) {
                        $arh_key = preg_replace($rx_http, '', $key);
                        $rx_matches = array();
                        // do string manipulations to restore the original letter case
                        $rx_matches = explode('_', $arh_key);
                        if( count($rx_matches) > 0 and strlen($arh_key) > 2 ) {
                                foreach($rx_matches as $ak_key => $ak_val) $rx_matches[$ak_key] = ucfirst($ak_val);
                                $arh_key = implode('-', $rx_matches);
                        }
                        $arh[$arh_key] = $val;
                }
        }
        return( $arh );
}

$app->run();
?>