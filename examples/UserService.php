<?php

declare(strict_types=1);

namespace Example;

/**
 * 用户服务类
 * 
 * 用于演示 AOP 功能
 */
class UserService
{
    /**
     * 创建用户
     *
     * @param array $userData 用户数据
     * @return array 创建的用户信息
     */
    public function createUser(array $userData): array
    {
        echo "Creating user with data: " . json_encode($userData) . "\n";
        
        // 模拟用户创建逻辑
        $user = [
            'id' => rand(1, 1000),
            'name' => $userData['name'] ?? 'Unknown',
            'email' => $userData['email'] ?? '',
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        return $user;
    }

    /**
     * 更新用户
     *
     * @param int $id 用户ID
     * @param array $userData 用户数据
     * @return array 更新后的用户信息
     */
    public function updateUser(int $id, array $userData): array
    {
        echo "Updating user {$id} with data: " . json_encode($userData) . "\n";
        
        // 模拟用户更新逻辑
        $user = [
            'id' => $id,
            'name' => $userData['name'] ?? 'Unknown',
            'email' => $userData['email'] ?? '',
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        return $user;
    }

    /**
     * 删除用户
     *
     * @param int $id 用户ID
     * @return bool 是否删除成功
     */
    public function deleteUser(int $id): bool
    {
        echo "Deleting user {$id}\n";
        
        // 模拟用户删除逻辑
        return true;
    }
}