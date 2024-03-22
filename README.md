## Embedded Relations for EasyAdminBundle 

Based on a pull request by [lukasluecke](https://github.com/lukasluecke)

https://github.com/EasyCorp/EasyAdminBundle/pull/3543

### Installation

I recommend that you don't. However:

```bash
composer require madforit/easyadmin-embed-bundle
```

### Usage

Change your CRUD controller to extend Madforit\EasyAdminEmbedBundle\Controller\AbstractCrudController

```php
class MyCrudController extends \Madforit\EasyAdminEmbedBundle\Controller\AbstractCrudController {
    
    public function configureFields(string $pageName): iterable {
        // Unfortunately you have to specify the template here
        yield EmbedField::new('relation_field')->setTemplatePath('@EasyAdminEmbed/crud/field/embed.html.twig')
    }
}
