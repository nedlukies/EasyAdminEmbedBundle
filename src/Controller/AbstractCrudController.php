<?php

namespace Madforit\EasyAdminEmbedBundle\Controller;

use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController as BaseAbstractCrudController;

use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use EasyCorp\Bundle\EasyAdminBundle\Exception\InsufficientEntityPermissionException;


use EasyCorp\Bundle\EasyAdminBundle\Event\AfterCrudActionEvent;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeCrudActionEvent;
use EasyCorp\Bundle\EasyAdminBundle\Exception\ForbiddenActionException;
use EasyCorp\Bundle\EasyAdminBundle\Factory\ActionFactory;
use EasyCorp\Bundle\EasyAdminBundle\Factory\EntityFactory;
use EasyCorp\Bundle\EasyAdminBundle\Factory\FieldFactory;
use EasyCorp\Bundle\EasyAdminBundle\Factory\FilterFactory;
use EasyCorp\Bundle\EasyAdminBundle\Factory\PaginatorFactory;
use EasyCorp\Bundle\EasyAdminBundle\Registry\TemplateRegistry;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use EasyCorp\Bundle\EasyAdminBundle\Security\Permission;
use ReflectionClass;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * AbstractCrudController
 * @see \EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController
 * @since 1.0.0
 */

abstract class AbstractCrudController extends BaseAbstractCrudController {

    private $called = 0;

    #[Required]
    public EntityFactory $entityFactory;
    #[Required]
    public FieldFactory $fieldFactory;
    #[Required]
    public ActionFactory $actionFactory;

    public function index(\EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext $context)
    {
        $this->called++;

        $reflection = new ReflectionClass($context);

        $i18nContextProperty = $reflection->getProperty('i18nContext');
        $i18nContextProperty->setAccessible(true);
        $i18nContext = $i18nContextProperty->getValue($context);
        $i18nContextReflection = new ReflectionClass($i18nContext);
        $templateRegistryProperty = $i18nContextReflection->getProperty( 'templateRegistry' );
        $templateRegistryProperty->setAccessible(true);
        $templateRegistry = $templateRegistryProperty->getValue( $i18nContext );

        $templateRegistryReflection = new ReflectionClass($templateRegistry);
        $templateRegistryTemplatesProperty = $templateRegistryReflection->getProperty('templates');
        $templateRegistryTemplatesProperty->setAccessible(true);
        $templateRegistryTemplates  = $templateRegistryTemplatesProperty->getValue($templateRegistry);
        $templateRegistryTemplatesProperty->setValue($templateRegistry, array_merge($templateRegistryTemplates, [
            'crud/index' => '@EasyAdminEmbed/crud/index.html.twig',
            'crud/embedded' => '@EasyAdminEmbed/crud/embedded.html.twig',
            'crud/field/embed' => '@EasyAdminEmbed/crud/field/embed.html.twig'
        ]) );
        $templateRegistryProperty->setValue($context, $templateRegistry);

        $event = new BeforeCrudActionEvent($context);
        $this->container->get('event_dispatcher')->dispatch($event);
        if ($event->isPropagationStopped()) {
            return $event->getResponse();
        }

        if (!$this->isGranted(Permission::EA_EXECUTE_ACTION, ['action' => Action::INDEX, 'entity' => null])) {
            throw new ForbiddenActionException($context);
        }


        $fields = FieldCollection::new($this->configureFields(Crud::PAGE_INDEX));

        $context->getCrud()->setFieldAssets($this->getFieldAssets($fields));
        $filters = $this->container->get(FilterFactory::class)->create($context->getCrud()->getFiltersConfig(), $fields, $context->getEntity());
        $queryBuilder = $this->createIndexQueryBuilder($context->getSearch(), $context->getEntity(), $fields, $filters);

        if(array_key_exists('embedContext', $context->getRequest()->query->all()) && $embedContext = $context->getRequest()->query->all()['embedContext'])
        {
            $filterProperty = $embedContext['mappedBy'];
            $filterValue = $embedContext['embeddedIn'];
            $queryBuilder->andWhere($queryBuilder->expr()->eq(sprintf('%s.%s', current($queryBuilder->getRootAliases()), $filterProperty), $filterValue));

            if ($fields->get($filterProperty)) {
                $fields->unset($fields->get($filterProperty));
            }
        }


        $paginator = $this->container->get(PaginatorFactory::class)->create($queryBuilder);

        // this can happen after deleting some items and trying to return
        // to a 'index' page that no longer exists. Redirect to the last page instead
        if ($paginator->isOutOfRange()) {
            return $this->redirect($this->container->get(AdminUrlGenerator::class)
                ->set(EA::PAGE, $paginator->getLastPage())
                ->generateUrl());
        }

        $entities = $this->entityFactory->createCollection($context->getEntity(), $paginator->getResults());
        $this->fieldFactory->processFieldsForAll($entities, $fields);
        $actions = $this->actionFactory->processGlobalActionsAndEntityActionsForAll($entities, $context->getCrud()->getActionsConfig());

        $responseParameters = $this->configureResponseParameters(KeyValueStore::new([
            'pageName' => Crud::PAGE_INDEX,
            'templateName' => isset($embedContext) ? 'crud/embedded' : 'crud/index',
            'entities' => $entities,
            'paginator' => $paginator,
            'global_actions' => $actions->getGlobalActions(),
            'batch_actions' => $actions->getBatchActions(),
            'filters' => $filters,
        ]));


        $event = new AfterCrudActionEvent($context, $responseParameters);
        $this->container->get('event_dispatcher')->dispatch($event);

        if ($event->isPropagationStopped()) {
            return $event->getResponse();
        }

        return $responseParameters;
    }

    public function detail(\EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext $context)
    {
        $event = new BeforeCrudActionEvent($context);
        $this->container->get('event_dispatcher')->dispatch($event);
        if ($event->isPropagationStopped()) {
            return $event->getResponse();
        }

        if (!$this->isGranted(Permission::EA_EXECUTE_ACTION, ['action' => Action::DETAIL, 'entity' => $context->getEntity()])) {
            throw new ForbiddenActionException($context);
        }

        if (!$context->getEntity()->isAccessible()) {
            throw new InsufficientEntityPermissionException($context);
        }

        $this->fieldFactory->processFields($context->getEntity(), FieldCollection::new($this->configureFields(Crud::PAGE_DETAIL)));
        $context->getCrud()->setFieldAssets($this->getFieldAssets($context->getEntity()->getFields()));
        $this->actionFactory->processEntityActions($context->getEntity(), $context->getCrud()->getActionsConfig());

        $responseParameters = $this->configureResponseParameters(KeyValueStore::new([
            'pageName' => Crud::PAGE_DETAIL,
            'templateName' => 'crud/detail',
            'entity' => $context->getEntity(),
        ]));

        $event = new AfterCrudActionEvent($context, $responseParameters);
        $this->container->get('event_dispatcher')->dispatch($event);
        if ($event->isPropagationStopped()) {
            return $event->getResponse();
        }

        return $responseParameters;
    }

}
