<?php
// {{{ICINGA_LICENSE_HEADER}}}
// {{{ICINGA_LICENSE_HEADER}}}

namespace Icinga\Module\Setup\Form;

use Exception;
use LogicException;
use Icinga\Application\Config;
use Icinga\Web\Form;
use Icinga\Data\ResourceFactory;
use Icinga\Authentication\Backend\DbUserBackend;
use Icinga\Authentication\Backend\LdapUserBackend;

/**
 * Wizard page to define the initial administrative account
 */
class AdminAccountPage extends Form
{
    /**
     * The resource configuration to use
     *
     * @var array
     */
    protected $resourceConfig;

    /**
     * The backend configuration to use
     *
     * @var array
     */
    protected $backendConfig;

    /**
     * Initialize this page
     */
    public function init()
    {
        $this->setName('setup_admin_account');
        $this->setViewScript('form/setup-admin-account.phtml');
    }

    /**
     * Set the resource configuration to use
     *
     * @param   array   $config
     *
     * @return  self
     */
    public function setResourceConfig(array $config)
    {
        $this->resourceConfig = $config;
        return $this;
    }

    /**
     * Set the backend configuration to use
     *
     * @param   array   $config
     *
     * @return  self
     */
    public function setBackendConfig(array $config)
    {
        $this->backendConfig = $config;
        return $this;
    }

    /**
     * @see Form::createElements()
     */
    public function createElements(array $formData)
    {
        $choices = array();

        if ($this->backendConfig['backend'] !== 'db') {
            $choices['by_name'] = mt('setup', 'By Name', 'setup.admin');
            $this->addElement(
                'text',
                'by_name',
                array(
                    'required'      => isset($formData['user_type']) && $formData['user_type'] === 'by_name',
                    'value'         => $this->getUsername(),
                    'label'         => mt('setup', 'Username'),
                    'description'   => mt(
                        'setup',
                        'Define the initial administrative account by providing a username that reflects'
                        . ' a user created later or one that is authenticated using external mechanisms'
                    )
                )
            );
        }

        if ($this->backendConfig['backend'] === 'db' || $this->backendConfig['backend'] === 'ldap') {
            $users = $this->fetchUsers();
            if (false === empty($users)) {
                $choices['existing_user'] = mt('setup', 'Existing User');
                $this->addElement(
                    'select',
                    'existing_user',
                    array(
                        'required'      => isset($formData['user_type']) && $formData['user_type'] === 'existing_user',
                        'label'         => mt('setup', 'Username'),
                        'description'   => sprintf(
                            mt(
                                'setup',
                                'Choose a user reported by the %s backend as the initial administrative account',
                                'setup.admin'
                            ),
                            $this->backendConfig['backend'] === 'db'
                                ? mt('setup', 'database', 'setup.admin.authbackend')
                                : 'LDAP'
                        ),
                        'multiOptions'  => array_combine($users, $users)
                    )
                );
            }
        }

        if ($this->backendConfig['backend'] === 'db') {
            $choices['new_user'] = mt('setup', 'New User');
            $required = isset($formData['user_type']) && $formData['user_type'] === 'new_user';
            $this->addElement(
                'text',
                'new_user',
                array(
                    'required'      => $required,
                    'label'         => mt('setup', 'Username'),
                    'description'   => mt(
                        'setup',
                        'Enter the username to be used when creating an initial administrative account'
                    )
                )
            );
            $this->addElement(
                'password',
                'new_user_password',
                array(
                    'required'      => $required,
                    'label'         => mt('setup', 'Password'),
                    'description'   => mt('setup', 'Enter the password to assign to the newly created account')
                )
            );
            $this->addElement(
                'password',
                'new_user_2ndpass',
                array(
                    'required'      => $required,
                    'label'         => mt('setup', 'Repeat password'),
                    'description'   => mt('setup', 'Please repeat the password given above to avoid typing errors'),
                    'validators'    => array(
                        array('identical', false, array('new_user_password'))
                    )
                )
            );
        }

        if (count($choices) > 1) {
            $this->addElement(
                'radio',
                'user_type',
                array(
                    'required'      => true,
                    'multiOptions'  => $choices
                )
            );
        } else {
            $this->addElement(
                'hidden',
                'user_type',
                array(
                    'required'  => true,
                    'value'     => key($choices)
                )
            );
        }

        $this->addElement(
            'note',
            'title',
            array(
                'value'         => mt('setup', 'Administration', 'setup.page.title'),
                'decorators'    => array(
                    'ViewHelper',
                    array('HtmlTag', array('tag' => 'h2'))
                )
            )
        );
        $this->addElement(
            'note',
            'description',
            array(
                'value' => tp(
                    'Now it\'s time to configure your first administrative account for Icinga Web 2.'
                    . ' Please follow the instructions below:',
                    'Now it\'s time to configure your first administrative account for Icinga Web 2.'
                    . ' Below are several options you can choose from. Select one and follow its instructions:',
                    count($choices)
                )
            )
        );
    }

    /**
     * Validate the given request data and ensure that any new user does not already exist
     *
     * @param   array   $data   The request data to validate
     *
     * @return  bool
     */
    public function isValid($data)
    {
        if (false === parent::isValid($data)) {
            return false;
        }

        if ($data['user_type'] === 'new_user' && array_search($data['new_user'], $this->fetchUsers()) !== false) {
            $this->getElement('new_user')->addError(mt('setup', 'Username already exists.'));
            return false;
        }

        return true;
    }

    /**
     * Return the name of the externally authenticated user
     *
     * @return  string
     */
    protected function getUsername()
    {
        if (false === isset($_SERVER['REMOTE_USER'])) {
            return '';
        }

        $name = $_SERVER['REMOTE_USER'];
        if (isset($this->backendConfig['strip_username_regexp']) && $this->backendConfig['strip_username_regexp']) {
            // No need to silence or log anything here because the pattern has
            // already been successfully compiled during backend configuration
            $name = preg_replace($this->backendConfig['strip_username_regexp'], '', $name);
        }

        return $name;
    }

    /**
     * Return the names of all users this backend currently provides
     *
     * @return  array
     *
     * @throws  LogicException  In case the backend to fetch users from is not supported
     */
    protected function fetchUsers()
    {
        if ($this->backendConfig['backend'] === 'db') {
            $backend = new DbUserBackend(ResourceFactory::createResource(new Config($this->resourceConfig)));
        } elseif ($this->backendConfig['backend'] === 'ldap') {
            $backend = new LdapUserBackend(
                ResourceFactory::createResource(new Config($this->resourceConfig)),
                $this->backendConfig['user_class'],
                $this->backendConfig['user_name_attribute'],
                $this->backendConfig['base_dn']
            );
        } else {
            throw new LogicException(
                sprintf(
                    'Tried to fetch users from an unsupported authentication backend: %s',
                    $this->backendConfig['backend']
                )
            );
        }

        try {
            return $backend->listUsers();
        } catch (Exception $e) {
            // No need to handle anything special here. Error means no users found.
            return array();
        }
    }
}