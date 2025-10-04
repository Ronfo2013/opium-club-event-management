<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Models\User;

class AuthController extends Controller
{
    /**
     * Login user and create token.
     */
    public function login(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'email' => 'required|email',
                'password' => 'required|string',
            ]);

            // For now, we'll use a simple admin check
            // In production, you should have a proper users table with roles
            if ($validated['email'] === 'admin@opiumpordenone.com' && 
                $validated['password'] === 'admin123') {
                
                $user = (object) [
                    'id' => 1,
                    'name' => 'Admin',
                    'email' => 'admin@opiumpordenone.com',
                ];

                $token = $user->createToken('admin-token')->plainTextToken;

                return response()->json([
                    'success' => true,
                    'data' => [
                        'user' => $user,
                        'token' => $token,
                    ],
                    'message' => 'Login effettuato con successo'
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Credenziali non valide'
            ], 401);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dati non validi',
                'errors' => $e->errors()
            ], 422);
        }
    }

    /**
     * Logout user and revoke token.
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            $request->user()->currentAccessToken()->delete();

            return response()->json([
                'success' => true,
                'message' => 'Logout effettuato con successo'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Errore durante il logout'
            ], 500);
        }
    }

    /**
     * Get authenticated user.
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $request->user()
        ]);
    }

    /**
     * Verify token validity.
     */
    public function verify(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            return response()->json([
                'success' => true,
                'data' => [
                    'user' => $user,
                    'authenticated' => true
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token non valido'
            ], 401);
        }
    }
}





