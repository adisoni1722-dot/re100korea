<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Clean extends Controller {
    public function __construct() {
        parent::__construct();
        admin_logincheck();

    }

    public function index() {

    }


}
