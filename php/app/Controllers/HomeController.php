<?php
/**
 * Home Controller
 * Handles home page and redirects
 */

class HomeController extends Controller
{
    /**
     * Home page
     */
    public function index(Request $request): Response
    {
        // If logged in, redirect to calendar
        if (Session::isAuthenticated()) {
            return $this->redirect('/calendar');
        }

        // Otherwise redirect to login
        return $this->redirect('/login');
    }
}
