<?php
/* Icinga Web 2 | (c) 2013-2015 Icinga Development Team | GPLv2+ */

namespace Icinga\Module\Monitoring\Forms\Setup;

use Icinga\Web\Form;
use Icinga\Application\Platform;

class BackendPage extends Form
{
    public function init()
    {
        $this->setName('setup_monitoring_backend');
    }

    public function createElements(array $formData)
    {
        $this->addElement(
            'note',
            'title',
            array(
                'value'         => $this->translate('Monitoring Backend', 'setup.page.title'),
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
                'value' => $this->translate(
                    'Please configure below how Icinga Web 2 should retrieve monitoring information.'
                )
            )
        );

        $this->addElement(
            'text',
            'name',
            array(
                'required'      => true,
                'value'         => 'icinga',
                'label'         => $this->translate('Backend Name'),
                'description'   => $this->translate('The identifier of this backend')
            )
        );

        $resourceTypes = array();
        if (Platform::hasMysqlSupport() || Platform::hasPostgresqlSupport()) {
            $resourceTypes['ido'] = 'IDO';
        }
        $resourceTypes['livestatus'] = 'Livestatus';

        $this->addElement(
            'select',
            'type',
            array(
                'required'      => true,
                'label'         => $this->translate('Backend Type'),
                'description'   => $this->translate(
                    'The data source used for retrieving monitoring information'
                ),
                'multiOptions'  => $resourceTypes
            )
        );
    }
}
