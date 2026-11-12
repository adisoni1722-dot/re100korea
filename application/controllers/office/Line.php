<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Line extends Controller
{
    public function __construct()
    {
        parent::__construct();
        model(['mlm_model']);
        loginCheck();
    }

    /** -----------------------------------------------------
     *  후원 기반 트리
     * ----------------------------------------------------- */
    public function sponsor($id = null)
    {
        $decrypt_id = decrypt_id($id);
        if ($decrypt_id === false) {
            show_error('잘못된 접근입니다.');
        }

        $lineSponsor  = $this->mlm_model->my_line($decrypt_id, 'sponsor');
        $lineReferrer = $this->mlm_model->my_line($decrypt_id, 'referrer');

        $data = [
            'line_json_sponsor'  => raw(json_encode($lineSponsor, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)),
            'line_json_referrer' => raw(json_encode($lineReferrer, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)),
        ];

        view('office/line_view', $data, false);
    }
}
