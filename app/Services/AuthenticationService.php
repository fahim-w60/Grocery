<?php 

namespace App\Services;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Mail\SendOtpMail;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Exception;



class AuthenticationService
{
    public function registerUser($data)
    {
        $otp = rand(100000, 999999);

        $user = User::where('email', $data['email'])->first();

        if ($user && is_null($user->email_verified_at)) {
            $user->update([
                'otp' => $otp,
                'otp_created_at' => Carbon::now(),
                'otp_expire_at' => now()->addMinutes(5),
            ]);
        } else {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'role' => $data['role'],
                'password' => Hash::make($data['password']),
                'otp' => $otp,
                'otp_created_at' => Carbon::now(),
                'otp_expire_at' => Carbon::now()->addMinutes(10),
            ]);
        }

        try {
            if (!empty($user->email)) {
                Mail::to($user->email)
                ->queue((new SendOtpMail($otp, $user))->onQueue('high'));
            }
            else {
                return response()->json([
                    'status' => false,
                    'message' => 'Email is not found for sending OTP.',
                ], 500);
            }
        } catch (Exception $e) {
            
            return response()->json([
                'status' => false,
                'message' => 'Failed to send OTP: ' . $e->getMessage(),
                
            ], 500);
            // throw new Exception('Failed to send OTP: ' . $e->getMessage());
        }

        return $user; 
    }

    public function loginuser($data)
    {
        $credentials = ['password' => $data['password']];

        if(!isset($data['email'])){
            throw new \Exception('Email is required.');
        }
        
        $credentials['email'] = $data['email'];
        $user = User::where('email', $credentials['email'])->first();

        if (!$user) {
            throw new \Exception('User not found.');
        }

        if (is_null($user->email_verified_at)) {
            throw new \Exception('Please verify your email address before logging in.');
        }

        if(!$token = JWTAuth::attempt($credentials)) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized.',
            ], 401);
        }

        return [
            'status' => true,
            'token' => $token,
            'user' => JWTAuth::user(),
        ];
    }


    public function logoutUser()
    {
        try {
            if(!auth()->check())
            {
                return response()->json(['error' => 'User not authenticated'], 401);
            }

            $token = JWTAuth::getToken();
            //dd($token);

            if(!$token)
            {
                return response()->json(['error' => 'Token not found'], 400);
            }

            JWTAuth::invalidate($token);

            return [
                'status' => true,
                'message' => 'User Logged out sucessfully',
            ];
        }
        catch (Exception $e)
        {
            return [
                'status' => false,
                'message' => 'Failed to logout: '. $e->getMessage(),
            ];
        }
        
    }
    public function getProfile()
    {
        if(!auth()->check())
        {
            return response()->json([
                'status' => false,
                'message' => 'User not authenticated',
            ], 401);
        }
        $user = JWTAuth::user();
        if ($user && !empty($user->photo) && !filter_var($user->photo, FILTER_VALIDATE_URL)) {
            $user->photo = asset($user->photo);
        }
        else{
            $user->photo = asset('uploads/profiles/no_image.jpeg');
        }
        return [
            'user' => $user,
        ];

    }

    public function verifyOtp($data)
    {
        
        $user = User::where('otp', $data['otp'])->first();

        if(!$user)
        {
            return response()->json([
                'message' => 'Invalid OTP.',
                'errors' => [
                    'otp' => ['Invalid OTP.']
                ]
            ], 422);
        }

        if($user->otp_expire_at < Carbon::now())
        {
            return response()->json([
                'message' => 'OTP expired. Please request a new OTP.',
                'errors' => [
                    'otp' => ['OTP expired. Please request a new OTP.']
                ]
            ], 422);
        }

        $user->email_verified_at = Carbon::now();
        $user->otp = null;
        $user->otp_created_at = null;
        $user->otp_expire_at = null;
        $user->save();

        $token = JWTAuth::fromUser($user);

        return [
            'user' => $user,
            'token' => $token,
            'status' => true,
        ];
    }

    public function forgetPassword($data)
    {
        $user = User::where('email', $data['email'])->first();

        if(!$user)
        {
            return response()->json([
                'message' => 'The email does not exist in our records.',
                'errors' => [
                    'email' => ['The email does not exist in our records.']
                ]
            ], 404);
        }

        $otp = rand(100000, 999999);

        $user->otp = $otp;
        $user->otp_created_at = Carbon::now();
        $user->otp_expire_at = Carbon::now()->addMinute(10);
        $user->save();

        try {
            if (!empty($user->email)) {
                Mail::to($user->email)
                ->queue((new SendOtpMail($otp, $user))->onQueue('high'));
            }
        } catch (Exception $e) {
      
            Log::error('Failed to send OTP: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'error' => 'Failed to send OTP.',
                'message' => $e->getMessage(), 
            ], 500);
        }
        return [
            'user' => $user,
        ];
    }
    public function changePassword($data)
    {
        if (!auth()->check()) {
            return response()->json([
                'message' => 'User not authenticated.',
                'errors' => [
                    'token' => ['Authentication token is missing or invalid.']
                ]
            ], 401);
        }

        $user = JWTAuth::user();

        $user->password = Hash::make($data['new_password']);
        $user->save();

        return [
            'user' => $user,
        ];
    }

    public function googleCallback()
    {
        try{

            $google_user = Socialite::driver('google')->stateless()->user();

            $user = User::where('google_id', $google_user->getId())->first();

            $mail_user = User::where('email',$google_user->getEmail())->first();

            if($mail_user)
            {
                $token = JWTAuth::fromUser($mail_user);

                return response()->json([
                    'status' => true,
                    'message' => 'User logged in successfully',
                    'token' => $token,
                    'user' =>$mail_user,
                ]);
            }

            else if(!$user)
            {

                $new_user = User::create([
                    'name' => $google_user->getName(),
                    'email' => $google_user->getEmail(),
                    'google_id' => $google_user->getId(),
                    'role' => 'user',
                ]);   
                
                
            }
            else{
                
                $token = JWTAuth::fromUser($user);

                return response()->json([
                    'status' => true,
                    'message' => 'User logged in successfully',
                    'token' => $token,
                    'user' => $user,
                ]);

            }
        }
        catch(\Throwable $th){
            dd('Something Went Wrong! '.$th->getMessage());
        }
    }

}
