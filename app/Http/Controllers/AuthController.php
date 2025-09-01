<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use App\Services\AuthenticationService;

use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\VerifyRequest;
use App\Http\Requests\Auth\ForgetPasswordRequest;
use App\Http\Requests\Auth\ChangePasswordRequest;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Exceptions\JWTException;
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
            if ($result instanceof JsonResponse) {
                return $result;
            }
            return response()->json([
                'status' => true,
                'message' => 'User logged in successfully',
                'token' => $result['token'],
                'user' => $result['user'],
            ], 200);
        } catch (\Exception $e) {
            $statusCode = 401; // Default status code for authentication failures
            $message = $e->getMessage();

            if (str_contains($message, 'Email is required')) {
                $statusCode = 400;
            } elseif (str_contains($message, 'User not found')) {
                $statusCode = 404;
            }

            return response()->json([
                'status' => false,
                'message' => $message,
            ], $statusCode);
        }
    }

    public function logout(Request $request)
    {
        try {
            $result = $this->authenticationService->logoutUser();
            if ($result instanceof JsonResponse) {
                return $result;
            }

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
        if ($result instanceof JsonResponse) {
            return $result;
        }

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

        // If service returned a JsonResponse (e.g., invalid/expired OTP), pass it through
        if ($result instanceof JsonResponse) {
            return $result;
        }

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
        if ($result instanceof JsonResponse) {
            return $result;
        }

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
        if ($result instanceof JsonResponse) {
            return $result;
        }

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
        if ($result instanceof JsonResponse) {
            return $result;
        }
        
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

    public function verifyToken(Request $request)
    {
        try {
            $token = $request->bearerToken() ?? $request->input('token');
            if (!$token) {
                return response()->json(['success' => false, 'message' => 'Token not provided.'], 400);
            }

            $payload = JWTAuth::setToken($token)->getPayload();
            return response()->json([
                'success' => true,
                'message' => 'Token is active.',
                'expires_at' => $payload->get('exp'),
                'user_id' => $payload->get('sub'),
            ]);
        } catch (TokenExpiredException $e) {
            return response()->json(['success' => false, 'message' => 'Token has expired.'], 401);
        } catch (TokenInvalidException $e) {
            return response()->json(['success' => false, 'message' => 'Token is invalid.'], 401);
        } catch (JWTException $e) {
            return response()->json(['success' => false, 'message' => 'Token is absent or invalid.'], 401);
        }
    }
}
