<?php

namespace App\Api\v1\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Validator;
use Illuminate\Support\Facades\Hash;
use App\Users;
use App\User_exts;
use App\Sessions;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Dingo\Api\Exception\StoreResourceFailedException;
use Dingo\Api\Exception\UpdateResourceFailedException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Dingo\Api\Routing\Helpers;

class UserController extends Controller
{
    use Helpers;

    public function __construct(Request $request)
    {
    	$this->request = $request;
    }

    public function signUp() 
    {
        UserController::signUpValidation($this->request);
        $user = new Users;
        $user->email = strtolower($this->request->email);
        $user->password = bcrypt($this->request->password);
        $user->first_name = $this->request->first_name;
        $user->last_name = $this->request->last_name;
        $user->gender = $this->request->gender;
        $user->birthday = $this->request->birthday;
        $user->save();
        $user_exts = new User_exts;
        $user_exts->user_id = $user->id;
        $user_exts->save();
        return $this->response->created();
    }

    public function getProfile($user_id) 
    {
        $user = Users::find($user_id);
        if(! is_null($user))
        {
            $profile = array('user_id' => $user->id, 'email' => $user->email, 'user_name' => $user->user_name, 
                'first_name' => $user->first_name, 'last_name' => $user->last_name, 'gender' => $user->gender,
                'birthday' => $user->birthday, 'role' => $user->role, 'address' => $user->address, 'mini_avatar' => $user->mini_avatar);
            return $this->response->array($profile);
        }
        return $this->response->errorNotFound();
    }

    public function getSelfProfile() 
    {
        return $this->getProfile($this->request->self_user_id);
    }

    public function updateSelfProfile() 
    {
        UserController::updateProfileValidation($this->request);
        UserController::updateProfileUpdate($this->request);
        return $this->response->created();
    }

    private function updateProfileUpdate(Request $request)
    {
        if(count($request->all()) == 0)
        {
            return $this->response->errorBadRequest();
        }
        $user = Users::find($request->self_user_id);
        if($request->has('first_name'))
        {
            $user->first_name = $request->first_name;
        }
        if($request->has('last_name'))
        {
            $user->last_name = $request->last_name;
        }
        if($request->has('gender'))
        {
            $user->gender = $request->gender;
        }
        if($request->has('birthday'))
        {
            $user->birthday = $request->birthday;
        }
        if($request->has('address'))
        {
            $user->address = $request->address;
        }
        $user->save();
    }

    public function updateAccount() 
    {
        $this->updateAccountValidation($this->request);
        if(count($this->request->all()) == 0)
        {
            return $this->response->errorBadRequest();
        }
        $user = Users::find($this->request->self_user_id);
        if($this->request->has('first_name'))
        {
            $user->first_name = $this->request->first_name;
        }
        if($this->request->has('last_name'))
        {
            $user->last_name = $this->request->last_name;
        }
        if($this->request->has('gender'))
        {
            $user->gender = $this->request->gender;
        }
        if($this->request->has('birthday'))
        {
            $user->birthday = $this->request->birthday;
        }
        if($this->request->has('user_name'))
        {
            $user->user_name = $this->request->user_name;
        }
        $user->save();
        return $this->response->created();
    }

    public function getAccount() 
    {
        $user = Users::find($this->request->self_user_id);
        if(! is_null($user))
        {
            $account = array('email' => $user->email, 'user_name' => $user->user_name, 
                'first_name' => $user->first_name, 'last_name' => $user->last_name, 'gender' => $user->gender,
                'birthday' => $user->birthday, 'phone' => $user->phone);
            return $this->response->array($account);
        }
        return $this->response->errorNotFound();
    }

    public function updatePassword() 
    {
        $this->updatePasswordValidation($this->request);
        $user = $user = Users::find($this->request->self_user_id);
        $password_right = Hash::check($this->request->old_password, $user->password);
        if (!$password_right)
        {
            $user->login_count++;
            $user->save();
            if($user->login_count >= 3)
            {
                $session = Sessions::find($this->request->self_session_id);
                $session->delete();
                throw new UnauthorizedHttpException(null, 'Incorrect password, automatically lougout');
            }
            throw new UnauthorizedHttpException(null, 'Incorrect password, you still have '.(3-$user->login_count).' chances');
        }
        $user->password = bcrypt($this->request->new_password);
        $user->login_count = 0;
        $user->save();
        return $this->response->created();
    }

    public function verifyPassword() 
    {
        if($this->request->has('password'))
        {
            $user = $user = Users::find($this->request->self_user_id);
            $password_right = Hash::check($this->request->password, $user->password);
            if (!$password_right)
            {
                $user->login_count++;
                $user->save();
                if($user->login_count >= 3)
                {
                    $session = Sessions::find($this->request->self_session_id);
                    $session->delete();
                    throw new UnauthorizedHttpException(null, 'Incorrect password, automatically lougout');
                }
                throw new UnauthorizedHttpException(null, 'Incorrect password, please verify your information!');
            }
            $user->login_count = 0;
            $user->save();
            return $this->response->created();
        }
        return $this->response->errorNotFound();
    }

    public function updateSelfStatus() 
    {
        $this->updateSelfStatusValidation($this->request);
        $user_exts = User_exts::find($this->request->self_user_id);
        if(! is_null($user_exts)) {
            if($this->request->has('status'))
            {
                $user_exts->status = $this->request->status;
            }
            if($this->request->has('message'))
            {
                $user_exts->message = $this->request->message;
            }
            if(!is_null($this->request->message) && empty($this->request->message))
            {
                $user_exts->message = null;
            }
            $user_exts->save();
            return $this->response->created();
        }
        return $this->response->errorNotFound();
    }    

    public function getSelfStatus() 
    {
        return $this->getStatus($this->request->self_user_id);
    }

    public function getStatus($user_id) 
    {
        $user_exts = User_exts::find($user_id);
        if(! is_null($user_exts)) {
            if($user_id != $this->request->self_user_id && $user_exts->status == 5)
            {
                $info[] = ['status' => 0, 'message' => $user_exts->message];
            }
            else
            {
                $info[] = ['status' => $user_exts->status, 'message' => $user_exts->message];
            }
            return $this->response->array($info);
        }
        return $this->response->errorNotFound();
    }

    private function signUpValidation(Request $request)
    {
        $input = $request->all();
        if($request->has('email'))
        {
            $input['email'] = strtolower($input['email']);
        }
        $validator = Validator::make($input, [
            'email' => 'required|unique:users,email|max:50|email',
            'password' => 'required|between:8,16',
            'first_name' => 'required|string|max:50',
            'last_name' => 'required|string|max:50',
            'gender' => 'required|in:male,female',
            'birthday' => 'required|date_format:Y-m-d|before:tomorrow|after:1900-00-00',
        ]);
        if($validator->fails())
        {
            throw new StoreResourceFailedException('Could not create new user.',$validator->errors());
            // throw new UnprocessableEntityHttpException('Could not create new user.');
        }
    }

    private function updateProfileValidation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'string|max:50',
            'last_name' => 'string|max:50',
            'gender' => 'in:male,female',
            'birthday' => 'date_format:Y-m-d|before:tomorrow|after:1900-00-00',
        ]);
        if($validator->fails())
        {
            throw new UpdateResourceFailedException('Could not update user profile.',$validator->errors());
        }
    }

    private function updateAccountValidation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'filled|string|max:50',
            'last_name' => 'filled|string|max:50',
            'gender' => 'in:male,female',
            'birthday' => 'filled|date_format:Y-m-d|before:tomorrow|after:1900-00-00',
            'phone' => 'filled|string|max:30',
            'user_name' => 'filled|unique:users,user_name|regex:/^[a-zA-Z][a-zA-Z0-9_]{5,29}$/',
        ]);
        if($validator->fails())
        {
            throw new UpdateResourceFailedException('Could not update user profile.',$validator->errors());
        }
    }

    private function updatePasswordValidation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'old_password' => 'required|string|between:8,16',
            'new_password' => 'required|string|between:8,16',
        ]);
        if($validator->fails())
        {
            throw new UpdateResourceFailedException('Could not update user password.',$validator->errors());
        }
    }

    private function updateSelfStatusValidation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'filled|required_without:message|integer|between:0,5',
            'message' => 'required_without:status|string|max:100',
        ]);
        if($validator->fails())
        {
            if(!is_null($request->message) && empty($request->message))
            {
                if($request->has('status'))
                {
                    $validator = Validator::make($request->all(), [
                        'status' => 'integer|between:0,5'
                    ]);
                    if($validator->fails())
                    {
                        throw new UpdateResourceFailedException('Could not update user status.',$validator->errors());
                    }
                }
                return;
            }
           
            throw new UpdateResourceFailedException('Could not update user status.',$validator->errors());
        }
    }

    public function updateEmail() {
        
    }

    public function verifyEmail() {
        
    }

    public function updatePhone() {
        
    }

    public function verifyPhone() {
        
    }
}
