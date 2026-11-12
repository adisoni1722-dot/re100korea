# MiniCI Framework

MiniCI는 CodeIgniter3와 유사한 구조를 가지면서도
서비스 컨테이너(DI) 통합 과 경량화를 목표로 개발 되었습니다.

MiniCI의 가장 큰 특징은 **서비스 컨테이너(DI)**  핵심 요소를 통합 하였습니다.
CodeIgniter3 호출 목록을 레거시 코드로 지원합니다.

```php

-----------------------------------------------
컨트롤러  > 뷰 (View)
-----------------------------------------------
* minici 스타일
view('test');

* ci3 스타일 지원
$this->load-view('test');

-----------------------------------------------
컨트롤러  > 모델 (Model)
-----------------------------------------------
* minici 스타일
model('member_model')

* ci3 스타일 지원
$this->load->model('member_model')

-----------------------------------------------
컨트롤러 > 라이브러리(Library)
-----------------------------------------------
* minici 스타일
library('email');

* ci3 스타일 지원
$this->load->library('email');

-----------------------------------------------
컨트롤러  > 헬퍼(Helper)
-----------------------------------------------
* minici 스타일
helper('html');

* ci3 스타일 지원
$this->load->helper('html');

-----------------------------------------------
컨트롤러  > 세션 (Session)
-----------------------------------------------
* minici 스타일
$this->session->set('foo', 'bar')
$this->session->get('member_id')

* ci3 스타일 지원
$this->session->userdata('member_id');

-----------------------------------------------
컨트롤러  > 인풋 (input)
-----------------------------------------------
* minici 스타일
$this->input->post($key = null, $default = null, bool $xss_clean = false)

// 'page' 파라미터가 넘어오지 않으면 자동으로 1을 반환
$page = $this->input->post('page', 1);


-----------------------------------------------
 컨트롤러 > 폼검증 (validator)
-----------------------------------------------
* minici, Ci3 스타일

$this->form->set_rules('email', '이메일', 'required|valid_email');
if (!$this->form->run())


-----------------------------------------------
뷰파일 > 세션 (Session)
-----------------------------------------------
* minici 스타일
// 1. 'member_id' 세션 값을 가져옵니다. 값이 없으면 null을 반환합니다.
$member_id = session('member_id');

// 2. 'username' 세션 값을 가져옵니다. 값이 없으면 'Guest'를 반환합니다.
$username = session('name', 'Guest');

// 3. 보안 처리를 원치 않는 'html_content' 세션 값을 가져옵니다.
$raw_html = session('html_content', null, false);

-----------------------------------------------
뷰파일 > 인풋 (input)
-----------------------------------------------
* minici 스타일
// 모든 GET 데이터(배열)를 가져옵니다.
input('get')


//모든 POST 데이터(배열)를 가져옵니다
input('post')

// URL 쿼리스트링에서 'page' 값을 가져옵니다.
$page = input('get', 'page');

// POST 요청에서 'username' 값을 가져옵니다.
$username = input('post', 'username');

// GET/POST에 관계없이 'search_term' 값을 가져옵니다.
$search_term = input('var', 'search_term');

// 요청 헤더에서 'Accept-Language' 값을 가져옵니다.
$lang = input('header', 'Accept-Language');

// 클라이언트의 IP 주소를 가져옵니다.
$ip_address = input('ip'); // 키($key)를 지정하지 않습니다.

-----------------------------------------------
컨테이너
-----------------------------------------------
$db = app('db'); // 이 코드는 내부적으로 Container::get('db')를 호출합니다.
$result = $db->query("SELECT * FROM members");


-----------------------------------------------
미지원 사항
-----------------------------------------------
뷰퍼일에서 form_open() 및 form_close() 함수는 지원 하지 않습니다.
컨트롤러에서 $this->model(), $this->view(),$this->helper(),$this->libraries()는 사용하지 않습니다.

```
## 기본 디렉터리 구조

```
/
├── application/
│   ├── config/
│   │   ├── app.php
│   │   ├── autoload.php
│   │   └── config.php
│   ├── controllers/
│   ├── helpers/
│   ├── libraries/
│   ├── middlewares/
│   └── models/
│
│
├── system/
│   ├── services/
│   │    ├── Security.php
│   │    └── View.php
│   │
│   ├── Container.php
│   ├── Controller.php
│   ├── Middleware.php
│   ├── Router.php
│   ├── Common.php
│   └── ... (기타 코어 파일)
|
├── views/
|    └── _layout.php
├── .env
└──  index.php

```
