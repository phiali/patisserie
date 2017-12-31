<?php

namespace Patisserie;

class Auth
{
    public function check()
    {
        return isset($_SESSION['user']);
    }

    public function attempt($username, $password)
    {
        $yamlFile = PUBLIC_FOLDER . '/../config/site.yaml';
        $siteConfiguration = \Symfony\Component\Yaml\Yaml::parse(file_get_contents($yamlFile));

        if (   $username === $siteConfiguration['username']
            && password_verify($password, $siteConfiguration['password'])) {
            $_SESSION['user'] = $username;
            return true;
        }

        return false;
    }

    public function logout()
    {
        unset($_SESSION['user']);
    }
}