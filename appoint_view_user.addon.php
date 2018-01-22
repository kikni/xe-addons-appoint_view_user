<?php
    /* Copyright (C) KIKNI <http://www.kikni.com> */
    if(!defined('__XE__')) exit();
    /**
     * @appoint_view_user.addon.php
     * @author 키큰아이(developer@kikni.com)
     * @brief 문서보기 지정 에드온
     **/
    //에러시 패스
    if($this->error) return;
    
    $lang->avu_nick_name = '닉네임';
    $lang->avu_user_id = '아이디';
    $lang->avu_default_group_1 = '준회원';
    $lang->avu_default_group_2 = '정회원';
    $lang->avu_admin_group = '관리그룹';
    $lang->avu_all_group = '그룹 ';
    $lang->avu_grant = '권한 설정';
    $lang->avu_first_desc = '해당 "';
    $lang->avu_middle_desc = '"에 한하여 보기 권한을 부여합니다.';
    $lang->avu_last_desc = '(복수 등록은 , 로 구분)';
    $lang->avu_list_desc = '님만 보실 수 있어요.';

    $addon_idx = 909090;
    $addon_opt1 = $addon_info->is_nickname == 'S'? $lang->avu_all_group : ( $addon_info->is_nickname == 'Y' ? $lang->avu_nick_name : $lang->avu_user_id ); // 그룹, 이름, 닉네임 설정
    $addon_group_list = $addon_info->group_list ? $addon_info->group_list : $lang->avu_default_group_1.','.$lang->avu_default_group_2.','.$lang->avu_admin_group; // 준회원, 정회원, 관리그룹 기본값 설정

    if($called_position == 'after_module_proc'){
        if($this->act=='dispBoardWrite'){

            $val = null;
            $val->module_srl = $this->module_srl;
            $val->idx = $addon_idx;
            $val->name = $lang->avu_grant; //권한
            $val->type = $addon_info->is_nickname == 'S'?'checkbox':'text';
            $val->default = $addon_info->is_nickname == 'S'?$addon_group_list:'';
            $val->desc = $lang->avu_first_desc.$addon_opt1.$lang->avu_middle_desc.($addon_info->is_nickname == 'S'?'':$lang->avu_last_desc);
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
                    $args->is_secret = 'SECRET';
                    if($addon_info->is_display_user == 'Y'){
                        $args->title = $addon_opt1.$val.$lang->avu_list_desc;
                    }
                }

                $tmp_output = executeQuery('addons.appoint_view_user.updateDocumentExtra', $args);
            }
        }elseif(($this->act=='dispBoardContent' || $this->act=='getBoardCommentPage') && Context::get('document_srl')){
            $document_srl = Context::get('document_srl');

            // 권한이 있으면 권한을 제거후 읽기만 가능하게
            if($GLOBALS['own_document'][$document_srl] && $GLOBALS['appoint_view_user'][$document_srl]){
                unset($GLOBALS['own_document'][$document_srl]);
                $oDocument = Context::get('oDocument');
                $oDocument->variables['status'] = 'PUBLIC';
                Context::set('oDocument', $oDocument);
            }

            unset($GLOBALS['appoint_view_user'][$document_srl]);

        }

    }elseif($called_position == 'before_module_proc'){
        if(($this->act=='dispBoardContent' || $this->act=='getBoardCommentPage') && Context::get('document_srl')){

            unset($GLOBALS['appoint_view_user'][$document_srl]);
            $document_srl = Context::get('document_srl');

            $logged_info = Context::get('logged_info');
            if(!$logged_info || $logged_info->is_admin == 'Y' || $logged_info->denied =='Y' || $GLOBALS['own_document'][$document_srl]) return;

            $args->document_srl = $document_srl;
            $tmp_output = executeQuery('addons.appoint_view_user.getDocumentExtra', $args);

            if($tmp_output->toBool()){
                $extra_vars=unserialize($tmp_output->data->extra_vars);
                if($extra_vars->avuser){
                    if($addon_info->is_nickname == 'S'){
                        $users = $logged_info->group_list;
                        $avuser = explode(',',$extra_vars->avuser);
                        foreach($avuser as $l_user){
                            $GLOBALS['appoint_view_user'][$document_srl] = $l_user && in_array($l_user, $users);
                            if($GLOBALS['appoint_view_user'][$document_srl]) break;
                        }
                    }else{
                        $users = explode(',',$extra_vars->avuser);
                        $l_user = ($addon_info->is_nickname == 'Y'?$logged_info->nick_name:$logged_info->user_id);
                        $GLOBALS['appoint_view_user'][$document_srl] = $l_user && in_array($l_user,$users);
                    }

                    // 권한을 줘서 모든 정보를 얻어옴
                    $GLOBALS['own_document'][$document_srl] = $GLOBALS['appoint_view_user'][$document_srl];
                }
            }
        }
    }
?>
