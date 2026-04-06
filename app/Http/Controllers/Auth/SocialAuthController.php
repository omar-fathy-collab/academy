<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class SocialAuthController extends Controller
{
    // Google OAuth
    public function redirectToGoogle()
    {
        $clientId = config('services.google.client_id');
        $redirectUri = urlencode(config('services.google.redirect'));
        $scope = urlencode('email profile');

        $url = 'https://accounts.google.com/o/oauth2/v2/auth?'.
               "client_id={$clientId}".
               "&redirect_uri={$redirectUri}".
               '&response_type=code'.
               "&scope={$scope}".
               '&access_type=offline'.
               '&prompt=select_account';

        return redirect($url);
    }

    public function handleGoogleCallback(Request $request)
    {
        $code = $request->get('code');

        if (! $code) {
            return redirect()->route('login')
                ->withErrors(['error' => 'فشل في عملية المصادقة مع Google.']);
        }

        // Exchange code for access token
        $tokenResponse = $this->getGoogleAccessToken($code);

        if (! isset($tokenResponse['access_token'])) {
            Log::error('Google token exchange failed', $tokenResponse);

            return redirect()->route('login')
                ->withErrors(['error' => 'فشل في الحصول على رمز الوصول من Google.']);
        }

        $accessToken = $tokenResponse['access_token'];

        // Get user info from Google
        $userInfo = $this->getGoogleUserInfo($accessToken);

        if (! $userInfo) {
            return redirect()->route('login')
                ->withErrors(['error' => 'فشل في الحصول على معلومات المستخدم من Google.']);
        }

        // Find user by email
        $user = User::where('email', $userInfo['email'])
            ->where('is_active', 1)
            ->first();

        if (! $user) {
            // User doesn't exist - redirect with error
            return redirect()->route('login')
                ->withErrors(['error' => 'لا يوجد حساب مرتبط بهذا البريد الإلكتروني. يرجى التسجيل أولاً.']);
        }

        // Login the user
        Auth::login($user);

        // Redirect to dashboard
        return $this->redirectToDashboard($user);
    }

    // GitHub OAuth
    public function redirectToGithub()
    {
        $clientId = config('services.github.client_id');
        $redirectUri = urlencode(config('services.github.redirect'));
        $scope = urlencode('user:email');

        $url = 'https://github.com/login/oauth/authorize?'.
               "client_id={$clientId}".
               "&redirect_uri={$redirectUri}".
               "&scope={$scope}".
               '&response_type=code';

        return redirect($url);
    }

    public function handleGithubCallback(Request $request)
    {
        $code = $request->get('code');

        if (! $code) {
            return redirect()->route('login')
                ->withErrors(['error' => 'فشل في عملية المصادقة مع GitHub.']);
        }

        // Exchange code for access token
        $accessToken = $this->getGithubAccessToken($code);

        if (! $accessToken) {
            return redirect()->route('login')
                ->withErrors(['error' => 'فشل في الحصول على رمز الوصول من GitHub.']);
        }

        // Get user info from GitHub
        $userInfo = $this->getGithubUserInfo($accessToken);

        if (! $userInfo) {
            return redirect()->route('login')
                ->withErrors(['error' => 'فشل في الحصول على معلومات المستخدم من GitHub.']);
        }

        // Get user email (might need separate API call for private emails)
        $email = $this->getGithubPrimaryEmail($accessToken);

        if (! $email) {
            return redirect()->route('login')
                ->withErrors(['error' => 'لا يمكن الوصول إلى البريد الإلكتروني من GitHub.']);
        }

        // Find user by email
        $user = User::where('email', $email)
            ->where('is_active', 1)
            ->first();

        if (! $user) {
            // User doesn't exist - redirect with error
            return redirect()->route('login')
                ->withErrors(['error' => 'لا يوجد حساب مرتبط بهذا البريد الإلكتروني. يرجى التسجيل أولاً.']);
        }

        // Login the user
        Auth::login($user);

        // Redirect to dashboard
        return $this->redirectToDashboard($user);
    }

    // Helper methods for Google
    private function getGoogleAccessToken($code)
    {
        $clientId = config('services.google.client_id');
        $clientSecret = config('services.google.client_secret');
        $redirectUri = config('services.google.redirect');

        $url = 'https://oauth2.googleapis.com/token';

        $data = [
            'code' => $code,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code',
        ];

        $response = $this->makeHttpRequest($url, 'POST', $data);

        return json_decode($response, true);
    }

    private function getGoogleUserInfo($accessToken)
    {
        $url = 'https://www.googleapis.com/oauth2/v2/userinfo';

        $headers = [
            'Authorization: Bearer '.$accessToken,
            'Accept: application/json',
        ];

        $response = $this->makeHttpRequest($url, 'GET', [], $headers);

        return json_decode($response, true);
    }

    // Helper methods for GitHub
    private function getGithubAccessToken($code)
    {
        $clientId = config('services.github.client_id');
        $clientSecret = config('services.github.client_secret');
        $redirectUri = config('services.github.redirect');

        $url = 'https://github.com/login/oauth/access_token';

        $data = [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'code' => $code,
            'redirect_uri' => $redirectUri,
        ];

        $headers = [
            'Accept: application/json',
        ];

        $response = $this->makeHttpRequest($url, 'POST', $data, $headers);
        $responseData = json_decode($response, true);

        return $responseData['access_token'] ?? null;
    }

    private function getGithubUserInfo($accessToken)
    {
        $url = 'https://api.github.com/user';

        $headers = [
            'Authorization: token '.$accessToken,
            'Accept: application/json',
            'User-Agent: Your-App-Name',
        ];

        $response = $this->makeHttpRequest($url, 'GET', [], $headers);

        return json_decode($response, true);
    }

    private function getGithubPrimaryEmail($accessToken)
    {
        $url = 'https://api.github.com/user/emails';

        $headers = [
            'Authorization: token '.$accessToken,
            'Accept: application/json',
            'User-Agent: Your-App-Name',
        ];

        $response = $this->makeHttpRequest($url, 'GET', [], $headers);
        $emails = json_decode($response, true);

        foreach ($emails as $email) {
            if ($email['primary'] && $email['verified']) {
                return $email['email'];
            }
        }

        return null;
    }

    // Generic HTTP request method
    private function makeHttpRequest($url, $method = 'GET', $data = [], $headers = [])
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        }

        if (! empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            Log::error('CURL Error: '.$error);

            return false;
        }

        return $response;
    }

    // Redirect user based on role
    private function redirectToDashboard($user)
    {
        if ($user->isAdmin() || $user->role_id == 4) { // Keep 4 if not yet constantized, likely another admin variant
            return redirect()->route('dashboard');
        } elseif ($user->isTeacher()) {
            return redirect()->route('teacher.dashboard');
        } elseif ($user->isStudent()) {
            return redirect()->route('student.dashboard');
        }

        return redirect()->route('dashboard');
    }
}
