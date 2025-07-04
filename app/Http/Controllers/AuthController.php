<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use App\Services\AuthenticationService;

use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\VerifyRequest;
use App\Http\Requests\Auth\ForgetPasswordRequest;
use App\Http\Requests\Auth\ChangePasswordRequest;
use Exception;



class AuthController extends Controller
{
    protected $authenticationService;

    public function __construct(AuthenticationService $authenticationService)
    {
        $this->authenticationService = $authenticationService;    
    }

    public function register(RegisterRequest $request)
    {
        //dd($request->all());
        $validatedData = $request->validated();
        try {
            $user = $this->authenticationService->registerUser($validatedData);

            return response()->json([
                'status' => true,
                'message' => 'OTP sent to your email.Check spam if not found and verify',
                'user' => $user,
            ], 201);
        } 
        catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to register user: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function login(LoginRequest $request)
    {
        $validatedData = $request->validated();

        try {
            $result = $this->authenticationService->loginuser($validatedData);
            return response()->json([
                'status' => true,
                'message' => 'User Logged in successfully',
                'token' => $result['token'],
                'user' => $result['user'],
            ], 200);
        }
        catch (Exception $e)
        {
            return response()->json([
                'status' => false,
                'message' => 'Failed to login: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function logout(Request $request)
    {
        try {
            $result = $this->authenticationService->logoutUser();

            return response()->json([
                'status' => $result['status'],
                'message' => $result['message'],
            ], 200);
        }
        catch (Exception $e)
        {
            return response()->json([
                'status' => false,
                'message' => 'Failed to logout: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function profile()
    {
        $result = $this->authenticationService->getProfile();          

        return response()->json([
            'status' => true,
            'message' => 'user profile fetched successfully',
            'user' => $result['user'],
        ]);
    }

    public function verify(VerifyRequest $request)
    {
        $validatedData = $request->validated();

        $result = $this->authenticationService->verifyOtp($validatedData);

        return response()->json([
            'status' => true,
            'message' => 'User verified successfully',
            'user' => $result['user'],
            'token' => $result['token'],
        ]);

    }

    public function forgetPassword(ForgetPasswordRequest $request)
    {
        $validatedData = $request->validated();

        $result = $this->authenticationService->forgetPassword($validatedData);

        return response()->json([
            'status' => true,
            'message' => 'OTP sent to your email.Check spam if not found and Verify',
            'user' => $result['user'],
        ], 201);

    }

    public function chnagePassword(ChangePasswordRequest $request)
    {
        $validatedData = $request->validated();

        $result = $this->authenticationService->changePassword($validatedData);

        return response()->json([
            'status' => true,
            'message' => 'Password changed successfully',
            'user' => $result['user'],
        ], 200);
        
    }

    public function resendOTP(ForgetPasswordRequest $request)
    {
        $validatedData = $request->validated();

        $result = $this->authenticationService->forgetPassword($validatedData);
        
        return response()->json([
            'status' => true,
            'message' => 'OTP sent to your email.Check spam if not found and Verify',
            'user' => $result['user'],
        ], 201);
    }
    
    public function socialLogin(Request $request){

        return Socialite::drive('google')->stateless()->redirect();
    }


    public function googleCallback()
    {
        $result = $this->authenticationService->googleCallback();
    }
}
