<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserLoginRequest;
use App\Http\Requests\UserRegisterRequest;
use App\Http\Requests\VerifyEmailRequest;
use App\Mail\SendUserVerifyMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function register(UserRegisterRequest $request)
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'verification_token' => Str::random(64),
        ]);

        Mail::to($user->email)->send(new SendUserVerifyMail($user));
        return response()->json([
            'message' => 'Please check your email to verify your account.',
        ], 201);
    }

    public function verifyEmail(VerifyEmailRequest $request)
    {
        // Find the user by verification token
        $user = User::where('verification_token', $request->token)->first();

        if (!$user) {
            return response()->json(['message' => 'Invalid or expired token.'], 400);
        }

        // Verify the user and clear the token
        $user->verification_token = null;
        $user->email_verified_at = now(); // Mark the email as verified
        $user->save();

        // Generate API token after verification
        $token = $user->createToken('auth_token')->accessToken;

        return response()->json([
            'message' => 'Email successfully verified.',
            'access_token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    public function login(UserLoginRequest $request)
    {
        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        if (!$user->email_verified_at) {
            return response()->json(['message' => 'Please verify your email.'], 401);
        }

        $token = $user->createToken('auth_token')->accessToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
        ]);
    }
}
