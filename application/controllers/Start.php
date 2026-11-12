<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Start extends Controller
{
    public function __construct()
    {
    parent::__construct();
    }

    public function index()
    {

        $data['header'] = [
            'title' => SITE_DESC,
            'group' => 'main',
        ];

        view('home', $data);

    }

}
