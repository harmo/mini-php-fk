<?php
class Clan extends Model {

    public $id;
    public $name;
    public $mode;
    public $money;
    public $members;
    private $requires;
    public $owner;
    public $grades;

    public $available_modes = array(
        1 => 'Privé',
        2 => 'Public',
        3 => 'Sur demande'
    );
    public static $PRIVATE = 1;
    public static $PUBLIC = 2;
    public static $ON_DEMAND = 3;

    public $clan_chief_grade = 11;
    public $clan_member_grade = 999999;

    public function get($id){
        return $this->loadClan($this->selectOne('clan', '*', array('id' => (int)$id)));
    }

    public function getObject($id){
        $this->get($id);
        return $this;
    }

    public function getAll(){
        $clans = array();
        foreach($this->selectAll('clan', '*', null, 'ORDER BY name ASC') as $clan){
            $loaded_clan = $this->loadClan($clan);
            $clans[$loaded_clan->id] = $loaded_clan;
        }
        return $clans;
    }

    private function loadClan($clan){
        $loaded_clan = new stdClass();
        $loaded_clan->id    = $this->id     = $clan['id'];
        $loaded_clan->name  = $this->name   = $clan['name'];
        $loaded_clan->mode  = $this->mode   = $clan['mode'];
        $loaded_clan->money = $this->money  = $clan['money'];

        $grades = $this->selectAll('clan_grade', '*', array('id_clan' => $this->id), 'ORDER BY id_grade ASC');

        $loaded_members = array();
        $members = $this->selectAll('user', '*', array('clan' => $this->id));
        foreach($members as $member){
            $grade = false;
            if($member['clan_grade'] != null){
                $grade_class = new Grade();
                $grade = $grade_class->get((int)$member['clan_grade']);
                $grade->promotable = $grade->id > array_values($grades)[1]['id_grade'] ? true : false;
                $grade->demeanable = $grade->id < array_values($grades)[sizeof($grades) - 1]['id_grade'] ? true : false;
            }
            $loaded_members[$member['id']] = array(
                'login' => $member['login'],
                'grade' => $grade
            );
            if($member['id'] == $clan['owner']){
                $loaded_clan->owner = $this->owner  = $member;
            }
        }
        $loaded_clan->members = $this->members = $loaded_members;

        $loaded_requires = array();
        $requires = $this->selectAll('clan_require', '*', array('clan' => $this->id));
        foreach($requires as $require){
            $loaded_requires[$require['user']] = array(
                'id' => $require['id'],
                'message' => $require['message'],
                'user' => $this->selectOne('user', '*', array('id' => $require['user']))
            );
        }
        $loaded_clan->requires = $this->requires = $loaded_requires;

        $loaded_grades = array();
        foreach($grades as $grade){
            $perms = array_column($this->selectAll('grade_permission', 'id_permission', array('id_grade' => $grade['id_grade'])), 'id_permission');
            $loaded_grades[$grade['id_grade']] = $this->selectOne('grade', '*', array('id' => $grade['id_grade']));
            $permission_class = new Permission();
            $loaded_grades[$grade['id_grade']]['permissions'] = $permission_class->getMultiple(array_values($perms));
            $loaded_grades[$grade['id_grade']]['deletable'] = ($grade['id'] == array_values($grades)[0]['id'] || $grade['id'] == array_values($grades)[sizeof($grades) - 1]['id']) ? false : true;
        }
        $loaded_clan->grades = $this->grades = $loaded_grades;

        return $loaded_clan;
    }

    public function create($post){
        if(!isset($post['money']) || $post['money'] == ''){
            $post['money'] = 0;
        }

        $errors = $this->checkData($post);
        if(!empty($errors)){
            return array('in_error' => true, 'errors' => $errors);
        }

        $data = array(
            'name' => $post['name'],
            'mode' => (int)$post['mode'],
            'money' => $post['money'],
            'owner' => (int)$post['owner'],
        );
        $clan_id = $this->insertOne('clan', $data);
        if(!$clan_id){
            return array('in_error' => true, 'errors' => array('Enregistrement du clan impossible'));
        }

        $clan_grades = array(
            array('id_clan' => $clan_id, 'id_grade' => $this->clan_chief_grade),
            array('id_clan' => $clan_id, 'id_grade' => $this->clan_member_grade)
        );
        $this->insertMultiple('clan_grade', $clan_grades);

        foreach($post['members'] as $member_id){
            $user = $this->selectOne('user', '*', array('id' => (int)$member_id));
            $data = array(
                'clan' => $clan_id,
                'clan_grade' => ($user['id'] == $post['owner']) ? $this->clan_chief_grade : $this->clan_member_grade
            );
            $this->update('user', $data, array('id' => $user['id']));
        }

        return array('in_error' => false, 'success' => array('clan_id' => $clan_id));
    }

    public function updateData($post){
        if($post['money'] == ''){
            $post['money'] = 0;
        }

        $errors = $this->checkData($post);
        if(!empty($errors)){
            return array('in_error' => true, 'errors' => $errors);
        }

        $data = array(
            'name' => $this->escapeString($post['name']),
            'mode' => (int)$post['mode'],
            'money' => $post['money'],
            'owner' => (int)$post['owner'],
        );
        if(!$this->update('clan', $data, array('id' => $post['clan_id']))){
            return array('in_error' => true, 'errors' => array('Mise à jour du clan impossible'));
        }

        $user_class = new User();

        foreach($post['members'] as $member_id){
            $user = $this->selectOne('user', '*', array('id' => (int)$member_id));
            $data = array('clan' => $post['clan_id']);
            $this->update('user', $data, array('id' => $user['id']));
        }
        if(sizeof($this->members) != sizeof($post['members'])){
            $this->unsetUsersNotIn($post['members']);
        }

        return array('in_error' => false, 'success' => true);
    }

    public function remove(){
        $this->unsetUsersIn($this->members);
        $this->removeClanGrades();
        if(!$this->delete('clan', array('id' => $this->id))){
            return array('in_error' => true, 'errors' => array('Suppression du clan impossible'));
        }
        return array('in_error' => false, 'success' => true);
    }

    public function unsetUsersNotIn($data){
        foreach($this->members as $id => $login){
            if(!in_array($id, $data)){
                $this->update('user', array('clan' => 'NULL'), array('id' => $id));
            }
        }
    }

    public function unsetUsersIn($data){
        foreach($data as $id => $user){
            $this->update('user', array('clan' => 'NULL'), array('id' => $id));
        }
    }

    private function removeClanGrades(){
        $this->delete('clan_grade', array('id_clan' => $this->id));
    }

    private function checkData($data){
        $errors = array();

        if(!isset($data['name']) || $data['name'] == ''){
            $errors['name'] = 'Veuillez fournir le nom';
        }

        if(!isset($data['owner']) || $data['owner'] == ''){
            $errors['owner'] = 'Veuillez indiquer le chef du clan';
        }

        if(!isset($data['members']) || sizeof($data['members']) == 0){
            $errors['members'] = 'Veuillez selectionner au moins un membre';
        }

        return $errors;
    }

    public function addUser($user){
        $data = array('user_id' => $user->id, 'clan' => $this->id);
        return $user->updateData($data);
    }

    public function removeUser($user){
        $data = array('user_id' => $user->id, 'clan' => 'NULL');
        return $user->updateData($data);
    }

    public function requireInvitation($user, $message){
        $data = array(
            'user' => $user->id,
            'clan' => $this->id,
            'message' => $message
        );
        $require_id = $this->insertOne('clan_require', $data);
        if(!$require_id){
            return array('in_error' => true, 'errors' => array('Enregistrement de la demande impossible'));
        }

        array_push($this->requires, $data);

        return array('in_error' => false);
    }

    public function cancelInvitation($user){
        if(isset($this->requires[$user->id])){
            unset($this->requires[$user->id]);
            return $this->delete('clan_require', array('user' => $user->id, 'clan' => $this->id));
        }
        return false;
    }

    public function getRequire($require_id){
        return $this->selectOne('clan_require', '*', array('id' => (int)$require_id));
    }

    public function acceptRequire($require, $user){
        if(is_array($require) && is_object($user)){
            if($this->addUser($user)){
                if($this->delete('clan_require', array('id' => $require['id'], 'user' => $user->id))){
                    return array('in_error' => false);
                }
            }
            return array('in_error' => true, 'errors' => array('Impossible d\'accepter la demande'));
        }
        return array('in_error' => true, 'errors' => array('Mauvais paramètres'));
    }

    public function refuseRequire($require, $user){
        return array('in_error' => true, 'errors' => array('Not implemented yet'));
    }

    public function changeOwner($user){
        $data = array('owner' => $user->id);
        $this->owner = $user;
        if(!$this->update('clan', array('owner' => $user->id), array('id' => $this->id))){
            return array('in_error' => true, 'errors' => array('Impossible de changer de chef'));
        }
        return array('in_error' => false);
    }

    public function addGrade($post){
        $grade_class = new Grade();
        $post['type'] = 2;
        $created = $grade_class->create($post);
        if($created['in_error']){
            return $created;
        }
        $clan_grade = $this->insertOne('clan_grade', array('id_clan' => (int)$post['clan_id'], 'id_grade' => (int)$created['success']['grade_id']));
        if(!$clan_grade){
            return array('in_error' => true, 'message' => 'Impossible de créer le grade.');
        }

        $perms = array_column($this->selectAll('grade_permission', 'id_permission', array('id_grade' => $created['success']['grade_id'])), 'id_permission');
        $permission_class = new Permission();

        return array(
            'in_error' => false,
            'id_clan_grade' => $clan_grade,
            'id_grade' => $created['success']['grade_id'],
            'name' => $post['name'],
            'description' => $post['description'],
            'permissions' => $permission_class->getMultiple(array_values($perms))
        );
    }

    public function removeGrade($post){
        $grade_class = new Grade();
        $grade = $grade_class->getObject($post['id_grade']);
        return $grade->remove();
    }

    public function promoteMember($member_id, $current_grade_id){
        return $this->updateMemberGrade($member_id, $current_grade_id, -1);
    }

    public function demeanMember($member_id, $current_grade_id){
        return $this->updateMemberGrade($member_id, $current_grade_id, 1);
    }

    private function updateMemberGrade($member_id, $current_grade_id, $index){
        $current_index = array_search($current_grade_id, array_keys($this->grades));
        $new_grade_id = array_keys($this->grades)[$current_index + $index];
        $user_class = new user();
        $user_class = $user_class->getObject($member_id);
        $update = $user_class->updateData(array('user_id' => $member_id, 'clan_grade' => $new_grade_id));
        if($update['in_error']){
            return $update;
        }
        return array('in_error' => false, 'grade' => $this->grades[$new_grade_id]['name']);
    }

    public function removeMember($member_id){
        $user_class = new user();
        $user_class = $user_class->getObject($member_id);
        return $user_class->updateData(array('user_id' => $member_id, 'clan' => 'NULL'));
    }
}