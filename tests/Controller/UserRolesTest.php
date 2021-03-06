<?php

namespace AlterPHP\EasyAdminExtensionBundle\Tests\Controller;

use AlterPHP\EasyAdminExtensionBundle\Tests\Fixtures\AbstractTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

class UserRolesTest extends AbstractTestCase
{
    public function setUp()
    {
        parent::setUp();

        $this->initClient(array('environment' => 'user_roles'));
    }

    private function logIn($roles = ['ROLE_ADMIN'])
    {
        $session = $this->client->getContainer()->get('session');

        // the firewall context defaults to the firewall name
        $firewallContext = 'secured_area';

        $token = new UsernamePasswordToken('admin', null, $firewallContext, $roles);
        $session->set('_security_'.$firewallContext, serialize($token));
        $session->save();

        $cookie = new Cookie($session->getName(), $session->getId());
        $this->client->getCookieJar()->set($cookie);
    }

    public function testAdminIsNotReachableWithoutMinimumRole()
    {
        $this->logIn(['ROLE_CATEGORY_LIST']);

        $this->client->followRedirects();

        $crawler = $this->getBackendPage();

        $this->assertSame(403, $this->client->getResponse()->getStatusCode());

        $this->assertSame(
            'You must be granted ROLE_ADMIN role to access admin ! (403 Forbidden)',
            trim($crawler->filterXPath('//head/title')->text())
        );
    }

    public function testAdminIsReachableWithMinimumRole()
    {
        $this->logIn(['ROLE_ADMIN', 'ROLE_CATEGORY_LIST']);

        $this->client->followRedirects();

        $crawler = $this->getBackendPage();

        $this->assertSame(200, $this->client->getResponse()->getStatusCode());
    }

    public function testMenuIsWellPruned()
    {
        $this->logIn(['ROLE_ADMIN', 'ROLE_CATEGORY_LIST']);

        $this->client->followRedirects();

        $crawler = $this->getBackendPage();

        $this->assertSame(200, $this->client->getResponse()->getStatusCode());

        $this->assertSame(
            1,
            $crawler->filter('body ul.sidebar-menu li:contains("Catalog")')->count()
        );
        $this->assertSame(
            1,
            $crawler->filter('body ul.sidebar-menu li ul li:contains("Categories")')->count()
        );
        $this->assertSame(
            0,
            $crawler->filter('body ul.sidebar-menu li ul li:contains("Products")')->count()
        );
        $this->assertSame(
            0,
            $crawler->filter('body ul.sidebar-menu li:contains("Images")')->count()
        );
        $this->assertSame(
            0,
            $crawler->filter('body ul.sidebar-menu li:contains("Sales")')->count()
        );
        $this->assertSame(
            0,
            $crawler->filter('body ul.sidebar-menu li ul li:contains("Purchases")')->count()
        );
        $this->assertSame(
            0,
            $crawler->filter('body ul.sidebar-menu li ul li:contains("Purchases items")')->count()
        );
    }

    public function testEntityActionsAreFilteredOnPrefixedRoles()
    {
        $this->logIn(['ROLE_ADMIN', 'ROLE_CATEGORY_LIST', 'ROLE_CATEGORY_SHOW']);

        $this->client->followRedirects();

        $this->getBackendPage(['entity' => 'Category', 'action' => 'list']);
        $this->assertSame(200, $this->client->getResponse()->getStatusCode());

        // Tests that embeddedList is mapped on list action required roles
        $this->getBackendPage(['entity' => 'Category', 'action' => 'embeddedList']);
        $this->assertSame(200, $this->client->getResponse()->getStatusCode());

        $crawler = $this->getBackendPage(['entity' => 'Category', 'action' => 'edit', 'id' => 1]);
        $this->assertSame(403, $this->client->getResponse()->getStatusCode());
        $this->assertSame(
            'You must be granted ROLE_CATEGORY_EDIT role to perform this entity action ! (403 Forbidden)',
            trim($crawler->filterXPath('//head/title')->text())
        );

        $this->getBackendPage(['entity' => 'Category', 'action' => 'show', 'id' => 1]);
        $this->assertSame(200, $this->client->getResponse()->getStatusCode());
    }

    public function testEntityActionsAreFilteredOnSpecificRoles()
    {
        $this->logIn(['ROLE_ADMIN', 'ROLE_PRODUCT_LIST', 'ROLE_TEST_EDIT_PRODUCT']);

        $this->client->followRedirects();

        $this->getBackendPage(['entity' => 'Product', 'action' => 'list']);

        $this->getBackendPage(['entity' => 'Product', 'action' => 'edit', 'id' => 1]);
        $this->assertSame(200, $this->client->getResponse()->getStatusCode());

        $crawler = $this->getBackendPage(['entity' => 'Product', 'action' => 'show', 'id' => 1]);
        $this->assertSame(403, $this->client->getResponse()->getStatusCode());
        $this->assertSame(
            'You must be granted ROLE_TEST_SHOW_PRODUCT role to perform this entity action ! (403 Forbidden)',
            trim($crawler->filterXPath('//head/title')->text())
        );
    }

    public function testAdminGroupRolesFormMayDisplay()
    {
        $this->logIn(['ROLE_ADMIN', 'ROLE_ADMINGROUP_EDIT']);

        $this->client->followRedirects();

        $crawler = $this->getBackendPage(['entity' => 'AdminGroup', 'action' => 'edit', 'id' => 1]);

        $this->assertSame(
            25,
            $crawler->filter('form#edit-admingroup-form .field-easyadmin_admin_roles input[type="checkbox"]')->count()
        );
    }
}
