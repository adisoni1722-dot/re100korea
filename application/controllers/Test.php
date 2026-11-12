<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Page extends Controller
{
    public function index()
    {
        model('member_model');
    }
}
