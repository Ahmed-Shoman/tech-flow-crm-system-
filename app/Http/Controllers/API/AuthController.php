<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    /**
     * Login user
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|min:6'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'details' => $validator->errors()
            ], 422);
        }

        $credentials = $request->only('email', 'password');

        if (Auth::attempt($credentials)) {
            $user = Auth::user();
            
            if (!$user->is_active) {
                return response()->json([
                    'success' => false,
                    'error' => 'Account is deactivated'
                ], 403);
            }

            // Update last login
            $user->update(['last_login_at' => now()]);

            // Create token
            $token = $user->createToken('API Token')->plainTextToken;

            // Log activity
            UserActivity::create([
                'user_id' => $user->id,
                'action' => 'login',
                'description' => 'User logged in',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'role' => $user->role,
                        'avatar_color' => $user->avatar_color,
                        'initials' => $user->initials
                    ],
                    'token' => $token
                ],
                'message' => 'Login successful'
            ]);
        }

        return response()->json([
            'success' => false,
            'error' => 'Invalid credentials'
        ], 401);
    }

    /**
     * Register new user (Admin only)
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
            'role' => 'required|in:admin,agent',
            'avatar_color' => 'nullable|string|max:7'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'details' => $validator->errors()
            ], 422);
        }

        // Check if current user is admin (except for first user)
        $userCount = User::count();
        if ($userCount > 0 && (!auth()->check() || !auth()->user()->isAdmin())) {
            return response()->json([
                'success' => false,
                'error' => 'Only admins can create new users'
            ], 403);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'avatar_color' => $request->avatar_color ?? '#3b82f6',
            'is_active' => true
        ]);

        // Log activity if authenticated
        if (auth()->check()) {
            UserActivity::create([
                'user_id' => auth()->id(),
                'action' => 'create_user',
                'description' => "Created new user: {$user->name} ({$user->role})",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => ['created_user_id' => $user->id]
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'avatar_color' => $user->avatar_color,
                    'initials' => $user->initials
                ]
            ],
            'message' => 'User created successfully'
        ], 201);
    }

    /**
     * Get authenticated user
     */
    public function user(Request $request)
    {
        $user = $request->user();
        
        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'avatar_color' => $user->avatar_color,
                    'initials' => $user->initials,
                    'last_login_at' => $user->last_login_at
                ]
            ]
        ]);
    }

    /**
     * Logout user
     */
    public function logout(Request $request)
    {
        // Log activity before logout
        UserActivity::create([
            'user_id' => auth()->id(),
            'action' => 'logout',
            'description' => 'User logged out',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent()
        ]);

        // Revoke current token
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout successful'
        ]);
    }

    /**
     * Update user profile
     */
    public function updateProfile(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|email|max:255|unique:users,email,' . auth()->id(),
            'avatar_color' => 'sometimes|string|max:7'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'details' => $validator->errors()
            ], 422);
        }

        $user = auth()->user();
        $oldData = $user->only(['name', 'email', 'avatar_color']);
        
        $user->update($request->only(['name', 'email', 'avatar_color']));

        // Log activity
        UserActivity::create([
            'user_id' => $user->id,
            'action' => 'update_profile',
            'description' => 'Updated profile information',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => ['old_data' => $oldData, 'new_data' => $request->only(['name', 'email', 'avatar_color'])]
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'avatar_color' => $user->avatar_color,
                    'initials' => $user->initials
                ]
            ],
            'message' => 'Profile updated successfully'
        ]);
    }

    /**
     * Change password
     */
    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required',
            'new_password' => 'required|string|min:6|confirmed'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'details' => $validator->errors()
            ], 422);
        }

        $user = auth()->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'error' => 'Current password is incorrect'
            ], 422);
        }

        $user->update(['password' => Hash::make($request->new_password)]);

        // Log activity
        UserActivity::create([
            'user_id' => $user->id,
            'action' => 'change_password',
            'description' => 'Changed password',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully'
        ]);
    }
}