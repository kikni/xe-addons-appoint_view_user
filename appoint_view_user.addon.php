<?php
    if(!defined("__ZBXE__")) exit();

    /**
     * @appoint_view_user.addon.php
     * @author phiDel(phidel@foxb.kr) fork kikni(developer@kikni.com)
     * @brief 문서보기 지정 에드온
     **/

    //에러시 패스
    if($this->error) return;

    $addon_idx = 909090;
    $addon_opt1 = $addon_info->is_nickname == 'S'?'소속 그룹':($addon_info->is_nickname == 'Y'?'닉네임':'아이디');
    $addon_group_list = $addon_info->group_list?$addon_info->group_list:'준회원,정회원,관리그룹';


    if($called_position == 'after_module_proc'){
        if($this->act=='dispBoardWrite'){

            $val = null;
            $val->module_srl = $this->module_srl;
            $val->idx = $addon_idx;
            $val->name = '문서보기 권한';
            $val->type = $addon_info->is_nickname == 'S'?'checkbox':'text';
            $val->default = $addon_info->is_nickname == 'S'?$addon_group_list:'';
            $val->desc = '지정한 유저 ('.$addon_opt1.') 에게만 문서보기 권한을 줍니다.'.($addon_info->is_nickname == 'S'?'':' (복수 등록은 , 로 구분)');
            $val->is_required = 'N';
            $val->search = 'N';
            $val->eid = 'addon_appoint_view_user';
            $val->value = '';

            $document_srl = Context::get('document_srl');

            if($document_srl)
            {
                $args->document_srl = $document_srl;
                $tmp_output = executeQuery('addons.appoint_view_user.getDocumentExtra', $args);
                if($tmp_output->toBool()){
                    $extra_vars=unserialize($tmp_output->data->extra_vars);
                    $val->value = $extra_vars->avuser;
                }
            }

            $obj = null;
            $obj = new ExtraItem($val->module_srl, $val->idx, $val->name, $val->type, $val->default, $val->desc, $val->is_required, $val->search, $val->value,  $val->eid);

            $extra_keys = Context::get('extra_keys');
            $extra_keys[$val->idx] = $obj;

            Context::set('extra_keys', $extra_keys);

        }elseif($this->act=='procBoardInsertDocument' && $this->variables['document_srl']){
            $val = Context::get('extra_vars'.$addon_idx);
            Context::set('extra_vars'.$addon_idx, null);

            if($val) $val = preg_replace("/\s+/","",$val);
            if($addon_info->is_nickname == 'S') $val = preg_replace("/\|\@\|/",",",$val);

            $args->document_srl = $this->variables['document_srl'];
            $tmp_output = executeQuery('addons.appoint_view_user.getDocumentExtra', $args);

            if($tmp_output->toBool()){
                $extra_vars=unserialize($tmp_output->data->extra_vars);
                if($val) $extra_vars->avuser = $val; else unset($extra_vars->avuser);
                $args->extra_vars = serialize($extra_vars);

                if($val){
                    unset($args->title);
                    $args->{preg_match('/^1.5/', __ZBXE_VERSION__) ? 'status' : 'is_secret'} = preg_match('/^1.5/', __ZBXE_VERSION__) ? 'SECRET' : 'Y';
                    if($addon_info->is_display_user == 'Y'){
                        $args->title = $addon_opt1.' "'.$val.'" 님만 보세요.';
                    }
                }

                $tmp_output = executeQuery('addons.appoint_view_user.updateDocumentExtra', $args);
            }
        }elseif(($this->act=='dispBoardContent' || $this->act=='getBoardCommentPage') && Context::get('document_srl')){
            $document_srl = Context::get('document_srl');

            // 권한이 있으면 권한을 제거후 읽기만 가능하게
            if($_SESSION['own_document'][$document_srl] && $_SESSION['appoint_view_user'][$document_srl]){
                unset($_SESSION['own_document'][$document_srl]);
                $oDocument = Context::get('oDocument');
                $oDocument->variables[preg_match('/^1.5/', __ZBXE_VERSION__) ? 'status' : 'is_secret'] = preg_match('/^1.5/', __ZBXE_VERSION__) ? 'PUBLIC' : 'N';
                Context::set('oDocument', $oDocument);
            }

            unset($_SESSION['appoint_view_user'][$document_srl]);

        }

    }elseif($called_position == 'before_module_proc'){
        if(($this->act=='dispBoardContent' || $this->act=='getBoardCommentPage') && Context::get('document_srl')){

            unset($_SESSION['appoint_view_user'][$document_srl]);
            $document_srl = Context::get('document_srl');

            $logged_info = Context::get('logged_info');
            if(!$logged_info || $logged_info->is_admin == 'Y' || $logged_info->denied =='Y' || $_SESSION['own_document'][$document_srl]) return;

            $args->document_srl = $document_srl;
            $tmp_output = executeQuery('addons.appoint_view_user.getDocumentExtra', $args);

            if($tmp_output->toBool()){
                $extra_vars=unserialize($tmp_output->data->extra_vars);
                if($extra_vars->avuser){
                    if($addon_info->is_nickname == 'S'){
                        $users = $logged_info->group_list;
                        $avuser = explode(',',$extra_vars->avuser);
                        foreach($avuser as $l_user){
                            $_SESSION['appoint_view_user'][$document_srl] = $l_user && in_array($l_user, $users);
                            if($_SESSION['appoint_view_user'][$document_srl]) break;
                        }
                    }else{
                        $users = explode(',',$extra_vars->avuser);
                        $l_user = ($addon_info->is_nickname == 'Y'?$logged_info->nick_name:$logged_info->user_id);
                        $_SESSION['appoint_view_user'][$document_srl] = $l_user && in_array($l_user,$users);
                    }

                    // 권한을 줘서 모든 정보를 얻어옴
                    $_SESSION['own_document'][$document_srl] = $_SESSION['appoint_view_user'][$document_srl];
                }
            }
        }
    }
?>
