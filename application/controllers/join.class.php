<?php
class Join extends Controller {

    function __construct(){
        parent::__construct();
    }

    function index(){
        $template = $this->loadView('front/join');
        $template->set('static', $this->staticFiles);
        $template->set('title', 'Nous rejoindre');
        $template->render();
    }

}