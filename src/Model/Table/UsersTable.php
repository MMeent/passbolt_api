<?php
/**
 * Passbolt ~ Open source password manager for teams
 * Copyright (c) Passbolt SARL (https://www.passbolt.com)
 *
 * Licensed under GNU Affero General Public License version 3 of the or any later version.
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Passbolt SARL (https://www.passbolt.com)
 * @license       https://opensource.org/licenses/AGPL-3.0 AGPL License
 * @link          https://www.passbolt.com Passbolt(tm)
 * @since         2.0.0
 */
namespace App\Model\Table;

use App\Model\Entity\Role;
use App\Model\Table\RolesTable;
use Aura\Intl\Exception;
use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * Users Model
 *
 * @property \App\Model\Table\RolesTable|\Cake\ORM\Association\BelongsTo $Roles
 * @property \App\Model\Table\FileStorageTable|\Cake\ORM\Association\HasMany $FileStorage
 * @property \App\Model\Table\GpgkeysTable|\Cake\ORM\Association\HasMany $Gpgkeys
 * @property \App\Model\Table\ProfilesTable|\Cake\ORM\Association\HasOne $Profiles
 * @property \App\Model\Table\GroupsTable|\Cake\ORM\Association\BelongsToMany $Groups
 *
 * @method \App\Model\Entity\User get($primaryKey, $options = [])
 * @method \App\Model\Entity\User newEntity($data = null, array $options = [])
 * @method \App\Model\Entity\User[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\User|bool save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\User patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\User[] patchEntities($entities, array $data, array $options = [])
 * @method \App\Model\Entity\User findOrCreate($search, callable $callback = null, $options = [])
 *
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class UsersTable extends Table
{

    /**
     * Initialize method
     *
     * @param array $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config)
    {
        parent::initialize($config);

        $this->setTable('users');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        $this->belongsTo('Roles', [
            'foreignKey' => 'role_id',
            'joinType' => 'INNER'
        ]);
        $this->hasMany('AuthenticationTokens', [
            'foreignKey' => 'user_id'
        ]);
        $this->hasMany('FileStorage', [
            'foreignKey' => 'user_id'
        ]);
        $this->hasOne('Gpgkeys', [
            'foreignKey' => 'user_id'
        ]);
        $this->hasOne('Profiles', [
            'foreignKey' => 'user_id',
        ]);
        $this->belongsToMany('Groups', [
            'through' => 'GroupsUsers'
        ]);
    }

    /**
     * Default validation rules.
     *
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator)
    {
        $validator
            ->uuid('id', __('User id by must be a valid UUID.'))
            ->allowEmpty('id', 'create');

        $validator
            ->requirePresence('username', 'create', __('A username is required.'))
            ->notEmpty('username', __('A username is required.'))
            ->maxLength('username', 255, __('The username length should be maximum 254 characters.'))
            ->email('username', true, __('The username should be a valid email address.'));

        $validator
            ->boolean('active')
            ->requirePresence('active', 'create')
            ->notEmpty('active');

        $validator
            ->boolean('deleted')
            ->requirePresence('deleted', 'create')
            ->notEmpty('deleted');

        $validator
            ->requirePresence('profile', 'create')
            ->notEmpty('profile');

        return $validator;
    }

    /**
     * Register validation rules.
     *
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
    public function validationRegister(Validator $validator)
    {
        return $this->validationDefault($validator);
    }

    /**
     * Register validation rules.
     *
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
    public function validationRecover(Validator $validator)
    {
        $validator
            ->requirePresence('username', 'create', __('A username is required.'))
            ->notEmpty('username', __('A username is required.'))
            ->maxLength('username', 255, __('The username length should be maximum 254 characters.'))
            ->email('username', true, __('The username should be a valid email address.'));

        return $validator;
    }

    /**
     * Returns a rules checker object that will be used for validating
     * application integrity.
     *
     * @param \Cake\ORM\RulesChecker $rules The rules object to be modified.
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules)
    {
        $rules->add($rules->isUnique(['username']), 'uniqueUsername', [
            'message' => __('This username is already in use.')
        ]);
        $rules->add($rules->existsIn(['role_id'], 'Roles'), 'validRole', [
            'message' => __('This is not a valid role')
        ]);

        return $rules;
    }

    /**
     * Build the query that fetches data for user index
     *
     * @param Query $query a query instance
     * @param array $options options
     * @throws Exception if no role is specified
     * @return Query
     */
    public function findIndex(Query $query, array $options)
    {
        // Options must contain a role
        if (!isset($options['role'])) {
            throw new Exception(__('User table findIndex should have a role set in options.'));
        }

        // Default associated data
        $query->contain([
            'Roles',
            'Profiles',
            //'Profiles.Avatar',
            'Gpgkeys',
//            'GroupsUsers'
        ]);

        // Filter out guests, inactive and deleted users
        $where = [
            'Users.deleted' => false,
            'Users.active' => true,
            'Roles.name <>' => Role::GUEST
        ];

        // if user is admin, we allow seing inactive users via the 'is-active' filter
        if ($options['role'] === Role::ADMIN) {
            if (isset($options['filter']['is-active'])) {
                $where['active'] = ($options['filter']['is-active'] ? true : false);
            }
        }
        $query->where($where);

        return $query;
    }

    /**
     * Find view
     *
     * @param Query $query a query instance
     * @param array $options options
     * @throws Exception if no id is specified
     * @return Query
     */
    public function findView(Query $query, array $options)
    {
        // Options must contain an id
        if (!isset($options['id'])) {
            throw new Exception(__('User table findView should have an id set in options.'));
        }
        // Same rule than index apply
        // with a specific id requested
        $query = $this->findIndex($query, $options);
        $query->where(['Users.id' => $options['id']]);

        return $query;
    }

    /**
     * Build the query that fetches the user data during authentication
     *
     * @param Query $query a query instance
     * @param array $options options
     * @throws Exception if fingerprint id is not set
     * @return Query $query
     */
    public function findAuth(Query $query, array $options)
    {
        // Options must contain an id
        if (!isset($options['fingerprint'])) {
            throw new Exception(__('User table findAuth should have a fingerprint id set in options.'));
        }
        // auth query is always done as guest
        $options['role'] = Role::GUEST;

        // Use default index option (active:true, deleted:false) and contains
        $query = $this->findIndex($query, $options);

        return $query->where(['Gpgkeys.fingerprint' => $options['fingerprint']]);
    }

    /**
     * Build the query that fetches data for user recovery
     *
     * @param string $username email of user to retrieve
     * @param array $options options
     * @return \Cake\ORM\Query
     */
    public function findRecover($username, array $options = [])
    {
        // show active first and do not count deleted ones
        $query = $this->find()
            ->where(['Users.username' => $username, 'Users.deleted' => false])
            ->contain(['Roles', 'Profiles']) // @TODO Avatar for recovery email
            ->order(['Users.active' => 'DESC']);

        return $query;
    }

    /**
     * Event fired before request data is converted into entities
     * Set user to inactive and not deleted on register
     *
     * @param \Cake\Event\Event $event event
     * @param \ArrayObject $data data
     * @param \ArrayObject $options options
     * @return void
     */
    public function beforeMarshal(\Cake\Event\Event $event, \ArrayObject $data, \ArrayObject $options)
    {
        if (isset($options['validate']) && $options['validate'] === 'register') {
            // Do not allow the user to set these flags
            $data['active'] = false;
            $data['deleted'] = false;

            // Set role to Role::USER by default
            $role = $this->Roles->find('all')
                ->where(['name' => Role::USER])
                ->first();
            $data['role_id'] = $role->id;
        }
    }
}