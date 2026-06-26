<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    /**
     * Display a listing of users
     */
    public function index(Request $request)
    {
        $users = User::select('id', 'name', 'email', 'role', 'avatar_color', 'is_active', 'last_login_at')
                     ->orderBy('created_at', 'desc')
                     ->get()
                     ->map(function ($user) {
                         return [
                             'id' => $user->id,
                             'name' => $user->name,
                             'email' => $user->email,
                             'role' => $user->role,
                             'avatar_color' => $user->avatar_color,
                             'initials' => $user->initials,
                             'is_active' => $user->is_active,
                             'last_login_at' => $user->last_login_at
                         ];
                     });

        // Log activity
        UserActivity::create([
            'user_id' => auth()->id(),
            'action' => 'view_users',
            'description' => 'Viewed users list',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent()
        ]);

        return response()->json([
            'success' => true,
            'data' => $users
        ]);
    }

    /**
     * Display the specified user
     */
    public function show(Request $request, User $user)
    {
        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'avatar_color' => $user->avatar_color,
                'initials' => $user->initials,
                'is_active' => $user->is_active,
                'last_login_at' => $user->last_login_at
            ]
        ]);
    }

    /**
     * Store a newly created user (Admin only)
     */
    public function store(Request $request)
    {
        // Check if current user is admin
        if (!auth()->user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'error' => 'Only admins can create new users'
            ], 403);
        }

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

        try {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => $request->role,
                'avatar_color' => $request->avatar_color ?? '#3b82f6',
                'is_active' => true
            ]);

            // Log activity
            UserActivity::create([
                'user_id' => auth()->id(),
                'action' => 'create_user',
                'description' => "Created new user: {$user->name} ({$user->role})",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => ['created_user_id' => $user->id]
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'avatar_color' => $user->avatar_color,
                    'initials' => $user->initials
                ],
                'message' => 'User created successfully'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to create user'
            ], 500);
        }
    }

    /**
     * Update the specified user (Admin only)
     */
    public function update(Request $request, User $user)
    {
        // Check if current user is admin
        if (!auth()->user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'error' => 'Only admins can update users'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|email|max:255|unique:users,email,' . $user->id,
            'role' => 'sometimes|in:admin,agent',
            'avatar_color' => 'sometimes|string|max:7',
            'is_active' => 'sometimes|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'details' => $validator->errors()
            ], 422);
        }

        try {
            $oldData = $user->only(['name', 'email', 'role', 'avatar_color', 'is_active']);
            
            $user->update($request->only(['name', 'email', 'role', 'avatar_color', 'is_active']));

            // Log activity
            UserActivity::create([
                'user_id' => auth()->id(),
                'action' => 'update_user',
                'description' => "Updated user: {$user->name}",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => [
                    'updated_user_id' => $user->id,
                    'changes' => array_diff_assoc($request->only(array_keys($oldData)), $oldData)
                ]
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'avatar_color' => $user->avatar_color,
                    'initials' => $user->initials,
                    'is_active' => $user->is_active
                ],
                'message' => 'User updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to update user'
            ], 500);
        }
    }

    /**
     * Change user password (Admin only)
     */
    public function changePassword(Request $request, User $user)
    {
        // Check if current user is admin or the user themselves
        if (!auth()->user()->isAdmin() && auth()->id() !== $user->id) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthorized'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'password' => 'required|string|min:6'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'details' => $validator->errors()
            ], 422);
        }

        try {
            $user->update(['password' => Hash::make($request->password)]);

            // Log activity
            UserActivity::create([
                'user_id' => auth()->id(),
                'action' => 'change_user_password',
                'description' => "Changed password for user: {$user->name}",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => ['target_user_id' => $user->id]
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Password changed successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to change password'
            ], 500);
        }
    }

    /**
     * Remove the specified user (Admin only)
     */
    public function destroy(Request $request, User $user)
    {
        // Check if current user is admin
        if (!auth()->user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'error' => 'Only admins can delete users'
            ], 403);
        }

        // Prevent deleting yourself
        if (auth()->id() === $user->id) {
            return response()->json([
                'success' => false,
                'error' => 'Cannot delete your own account'
            ], 422);
        }

        try {
            $userName = $user->name;
            $userId = $user->id;
            
            // Soft delete or hard delete based on your preference
            $user->delete();

            // Log activity
            UserActivity::create([
                'user_id' => auth()->id(),
                'action' => 'delete_user',
                'description' => "Deleted user: {$userName}",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => ['deleted_user_id' => $userId]
            ]);

            return response()->json([
                'success' => true,
                'message' => 'User deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to delete user'
            ], 500);
        }
    }

    /**
     * Get user activities
     */
    public function activities(Request $request, User $user)
    {
        $activities = UserActivity::where('user_id', $user->id)
                                  ->orderBy('created_at', 'desc')
                                  ->paginate($request->get('per_page', 50));

        return response()->json([
            'success' => true,
            'data' => $activities->items(),
            'pagination' => [
                'current_page' => $activities->currentPage(),
                'total_pages' => $activities->lastPage(),
                'per_page' => $activities->perPage(),
                'total' => $activities->total()
            ]
        ]);
    }
}
