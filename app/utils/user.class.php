<?php

  namespace App\Utils;
  
  use App\Router;
  
  class User {
    const USER_CREDENTIALS_FILE = 'files/private/.ht_user.json';
    const USER_OK = 0;
    const USER_NEED_LOGIN = 1;
    const USER_NEED_REGISTER = 2;
    private static $general_failure = '';
    private static $generic_login_error = '';
    
    static function main() {
      self::$general_failure = t('User general failure');
      self::$generic_login_error = t('Invalid username or password');
      self::checkPost();
      switch(self::userStatus()) {
        case self::USER_OK:
          setTitle(t('Change credentials'));
          return self::changeCredentialsForm();
        case self::USER_NEED_LOGIN:
          setTitle(config('sitename') . ' - ' . t('User login'));
          return self::loginForm();
        case self::USER_NEED_REGISTER:
          setTitle(t('User registration'));
          return self::changeCredentialsForm();
      }
      die(self::$general_failure);
    }
    
    static function validUser() {
      return self::userStatus() === self::USER_OK;
    }
    
    private static function validUsernameFormat($username) {
      $errors = [];
      if(strlen($username) < 8 ) {
        $errors[] = t('Username too short');
      }
      if(!preg_match("#[a-z]+#", $username)) {
        $errors[] = t('Username must include at least one lowercase letter!');
      }
      if(!preg_match("#[A-Z]+#", $username)) {
        $errors[] = t('Username must include at least one uppercase letter!');
      }
      foreach($errors as $error) {
        Msg::error($error);
      }
      return count($errors) === 0;
    }
    
    private static function passwordIsStrong($password) {
      $errors = [];
      if(strlen($password) < 8) {
        $errors[] = t('Password too short');
      }
      if(strlen($password) > 20) {
        $errors[] = t('Password too long!');
      }
      if(!preg_match("#[0-9]+#", $password)) {
        $errors[] = t('Password must include at least one number!');
      }
      if(!preg_match("#[a-z]+#", $password)) {
        $errors[] = t('Password must include at least one lowercase letter!');
      }
      if(!preg_match("#[A-Z]+#", $password)) {
        $errors[] = t('Password must include at least one uppercase letter!');
      }
      if(!preg_match("#\W+#", $password)) {
        $errors[] = t('Password must include at least one symbol!');
      }
      foreach($errors as $error) {
        Msg::error($error);
      }
      return count($errors) === 0;
    }
    
    private static function checkPost() {
      if(filter_input(INPUT_SERVER, 'REQUEST_METHOD') !== 'POST') {
        return;
      }
      $action = getPOST('action');
      if($action === 'logout') {
        session_destroy();
        Router::redirect();
      }
      $username = getPOST('username');
      $password = getPOST('password');
      $new_password = getPOST('new-password');
      $confirm_new_password = getPOST('confirm-new-password');
      switch($action) {
        case 'login':
          return self::processLogin($username, $password);
        case 'change-credentials':
          return self::processCredentials($username, $password, $new_password, $confirm_new_password);
      }
      die(self::$general_failure);      
    }
    
    private static function processLogin($username, $password) {
      $stored_user = self::getStoredUser();
      if($stored_user && $stored_user->username === $username && password_verify($password, $stored_user->password)) {
        self::setSessionUser($stored_user);
        Router::redirect();
      }
      else {
        Msg::error(self::$generic_login_error);
      }
    }
    
    private static function processCredentials($username, $password, $new_password, $confirm_new_password) {
      if(!self::validUser()) {
        self::processRegister($username, $new_password, $confirm_new_password);
      }
      else {
        self::processNewCredentials($username, $password, $new_password, $confirm_new_password);
      }
    }
    
    private static function processNewCredentials($username, $password, $new_password, $confirm_new_password) {
      $stored_user = self::getStoredUser();
      if(!password_verify($password, $stored_user->password)) {
        Msg::error(self::$generic_login_error);
        return;
      }
      if($new_password || $confirm_new_password) {
        if(!self::validNewUserCredentials($username, $new_password, $confirm_new_password)) {
          return;
        }
      }
      if(!self::validUsernameFormat($username)) {
        return;
      }
      $something_changed = false;
      if($username !== $stored_user->username) {
        Msg::msg(t(
          'Username changed from %s to %s', 
          $stored_user->username,
          $username
        ));
        $something_changed = true;
      }
      $new_user = self::createUserObject($username, $password);
      if($new_password) {
        $new_user = self::createUserObject($username, $new_password);
        Msg::msg(t('Password for user %s changed', $username));
        $something_changed = true;
      }
      if($something_changed) {
        self::setStoredUser($new_user);
      }
      else {
        Msg::warning(t('Nothing to do!'));
      }
    }
    
    private static function validNewUserCredentials($username, $new_password, $confirm_new_password) {
      if($new_password !== $confirm_new_password) {
        Msg::error(t('Provided passwords do not match'));
        return false;
      }
      if(!self::validUsernameFormat($username)) {
        return false;
      }
      if(!self::passwordIsStrong($new_password)) {
        return false;
      }
      return true;
    }
    
    private static function processRegister($username, $new_password, $confirm_new_password) {
      if(self::getStoredUser()) {
        Msg::error(t('New user registration forbidden'));
        return;
      }
      if(self::validNewUserCredentials($username, $new_password, $confirm_new_password)) {
        $user = self::createUserObject($username, $new_password);
        Msg::msg(t('User %s created successfully', $user->username));
        self::setStoredUser($user);
      }
    }
    
    private static function createUserObject($username, $password) {
      return (object)[
        'username' => $username,
        'password' => password_hash($password, PASSWORD_DEFAULT),
      ];
    }
    
    private static function getStoredUser() {
      $user_file = self::USER_CREDENTIALS_FILE;
      if(file_exists($user_file)) {
        $user = json_decode(file_get_contents($user_file));
        if(!is_null($user)) {
          return $user;
        }
      }
      return false;
    }
    
    private static function setStoredUser($user) {
      return 0 < file_put_contents(self::USER_CREDENTIALS_FILE, json_encode($user));
    }
    
    private static function getSessionUser() {
      if(isset($_SESSION['user'])) {
        return (object)$_SESSION['user'];
      }
    }
    
    private static function setSessionUser($user) {
      $_SESSION['user'] = (array)$user;
    }
    
    private static function userStatus() {
      $stored_user = self::getStoredUser();
      if(!$stored_user) {
        return self::USER_NEED_REGISTER;
      }
      $session_user = self::getSessionUser();
      if($stored_user != $session_user) {
        return self::USER_NEED_LOGIN;
      }
      return self::USER_OK;
    }
    
    static function loginForm() {
      Ob_start();
      ?>
<form method="POST" class="user">
  <table>
    <tbody>
      <tr>
        <td>
          <input type="text" name="username" placeholder="<?= t('username') ?>" required>
        </td>
      </tr>
      <tr>
        <td>
          <input type="password" name="password" placeholder="<?= t('password') ?>" required>
        </td>
      </tr>
    </tbody>
  </table>
  <button class="button login"
          type="submit" 
          name="action" 
          value="login"
  ><?= t('Send') ?></button>
</form>
      <?php
      return Ob_get_clean();
    }

    static function logoutForm() {
      Ob_start();
      ?>
<form method="POST" class="user" novalidate>
  <button class="button logout"
          type="submit"
          name="action"
          value="logout"
  ><?= t('Logout') ?></button>
</form>
      <?php
      return Ob_get_clean();
    }

    static function changeCredentialsForm() {
      $username = '';
      if(self::validUser()) {
        $stored = self::getStoredUser();
        if($stored) {
          $username = $stored->username;
        }
      }
      Ob_start();
      if($username) {
      ?>
<strong><?= $username ?></strong><?= self::logoutForm() ?>
      <?php
      }
      ?>
<form method="POST" class="user" novalidate>
  <table>
    <tbody>
      <tr>
        <td>
          <input type="text" 
                 name="username" 
                 placeholder="<?= t('username') ?>"
                 value="<?= $username ?>"
                 required>
        </td>
      </tr>
      <?php if(self::validUser()): ?>
      <tr>
        <td>
          <input type="password" name="password" placeholder="<?= t('current password') ?>" required>
        </td>
      </tr>
      <?php endif; ?>
      <tr>
        <td>
          <input type="password" name="new-password" placeholder="<?= t('new password') ?>" <?= $username?'':'required' ?>>
        </td>
      </tr>
      <tr>
        <td>
          <input type="password" name="confirm-new-password" placeholder="<?= t('confirm new password') ?>" <?= $username?'':'required' ?>>
        </td>
      </tr>
    </tbody>
  </table>
  <button class="button save"
          type="submit" 
          name="action" 
          value="change-credentials"
  ><?= t('Send') ?></button>
</form>
      <?php
      return Ob_get_clean();
    }
  }

