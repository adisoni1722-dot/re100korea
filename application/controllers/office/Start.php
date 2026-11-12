<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Start extends Controller
{
    public function __construct()
    {
        parent::__construct();
        model(['member_model']);
        loginCheck();
    }


    public function index()
    {
        $data['header'] = ['title' => '마이오피스', 'group' => 'main'];
        view('office/start', $data);
    }

    public function helpdesk()
    {
        $data['header'] = ['title' => '고객센터', 'group' => 'helpdesk'];
        view('office/help_desk', $data, false);
    }

    public function alram()
    {
        $data['header'] = ['title' => '알람설정', 'group' => 'helpdesk'];
        view('office/alram', $data, false);
    }
}
