<?php

namespace App\Traits;

use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Auth;

trait ValidatesPermissions
{
    /**
     * Validate that a user has a specific permission
     *
     * @param int|User $user
     * @param string $permission
     * @param string|null $customMessage
     * @throws Exception
     */
    protected function validatePermission($user, string $permission, ?string $customMessage = null): void
    {
        $userModel = $this->resolveUser($user);

        if (!$userModel->hasPermission($permission)) {
            $message = $customMessage ?? "No tienes permiso para realizar esta acción. Permiso requerido: {$permission}";
            throw new Exception($message);
        }
    }

    /**
     * Validate that a user has any of the specified permissions
     *
     * @param int|User $user
     * @param array $permissions
     * @param string|null $customMessage
     * @throws Exception
     */
    protected function validateAnyPermission($user, array $permissions, ?string $customMessage = null): void
    {
        $userModel = $this->resolveUser($user);

        foreach ($permissions as $permission) {
            if ($userModel->hasPermission($permission)) {
                return;
            }
        }

        $permissionsList = implode(', ', $permissions);
        $message = $customMessage ?? "No tienes ninguno de los permisos requeridos: {$permissionsList}";
        throw new Exception($message);
    }

    /**
     * Validate that a user has all of the specified permissions
     *
     * @param int|User $user
     * @param array $permissions
     * @param string|null $customMessage
     * @throws Exception
     */
    protected function validateAllPermissions($user, array $permissions, ?string $customMessage = null): void
    {
        $userModel = $this->resolveUser($user);

        foreach ($permissions as $permission) {
            if (!$userModel->hasPermission($permission)) {
                $message = $customMessage ?? "No tienes todos los permisos requeridos. Falta: {$permission}";
                throw new Exception($message);
            }
        }
    }

    /**
     * Check if user has a specific permission (returns boolean)
     *
     * @param int|User $user
     * @param string $permission
     * @return bool
     */
    protected function hasPermission($user, string $permission): bool
    {
        try {
            $userModel = $this->resolveUser($user);
            return $userModel->hasPermission($permission);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Validate that a user has a specific role
     *
     * @param int|User $user
     * @param string $role
     * @param string|null $customMessage
     * @throws Exception
     */
    protected function validateRole($user, string $role, ?string $customMessage = null): void
    {
        $userModel = $this->resolveUser($user);

        if (!$userModel->hasRole($role)) {
            $message = $customMessage ?? "No tienes el rol requerido: {$role}";
            throw new Exception($message);
        }
    }

    /**
     * Resolve user from ID or User model
     *
     * @param int|User $user
     * @return User
     * @throws Exception
     */
    protected function resolveUser($user): User
    {
        if ($user instanceof User) {
            return $user;
        }

        if (is_int($user)) {
            $userModel = User::find($user);
            if (!$userModel) {
                throw new Exception("Usuario no encontrado.");
            }
            return $userModel;
        }

        throw new Exception("Usuario inválido.");
    }
}
