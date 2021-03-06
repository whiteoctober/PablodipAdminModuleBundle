# More advanced usage

## Sorting

Applying sorting is actually really easy, just use the `sortable` parameter when adding model fields in the `configure` function.  For example:

    $modelFields->add(array(
        'lastName' => array(
            'label' => 'Last name',
            'sortable' => true,
        ),
    ));

### Sorting a table by default

To set the default sort order for a table, add the `sortable` parameter as above, and add code like the
following to configure the list action:

    $listAction = $this->getAction("list");
    $listAction->setOption("sort_default", "lastName");

## Configuring form fields

By default, the module will render you textboxes, checkboxes and drop-downs for dates, all worked out from the value you set for `template`.  However, you can instruct it to use any of [Symfony2's form types](http://symfony.com/doc/current/reference/forms/types.html) thanks to the `form_type` property!
You can also pass through settings for these types.  Here's an example:

    $modelFields->add(array(
        'votesPercent' => array(
            'label' => 'votes_percent',
            'form_type' => 'percent',
            'template' => 'PablodipAdminModuleBundle::fields/text.html.twig',
            'form_options' => array('required' => false, 'type' => 'integer')
        ),
    ));
    
### Validation groups

You can set a validation group for all form fields created by your module.  Override `defineConfiguration` like this:

    protected function defineConfiguration()
    {
        parent::defineConfiguration();

        $this->setOption('model_form_options', array('validation_groups' => array('your_group_here')));
    }

Note that, if you explicitly add a constraint to a field when configuring it in your module,
you'll need to set the validation group on that too, even if you've overridden `defineConfiguration` as above.
Here's an example:

        $modelFields->add(array(
            "new_password" => array(
                "form_type" => "password",
                "label" => "New password",
                "form_options" => array(
                    'constraints' => new Length(array(
                        'min' => 8,
                        'minMessage' => 'The new password must be at least {{ limit }} characters',
                        'groups' => 'your_group_here',
                    ))),
                ),
        ));

## Removing actions

You don't have to provide list, create, edit and delete for all your entities; you can remove the default actions if required.
  
For example, you could add some code like the following into your `configure` method to remove the add, edit and delete actions:

    foreach (array('new', 'create', 'edit', 'update', 'delete') as $action) {
        $this->deleteAction($action);
    }

You'd also need to remove the field actions from the list - see the next section.

If you're removing the ability to delete entities, you probably want to remove the "delete all selected items" batch option.
You could try and remove the `'batch'` option itself to remove the whole drop-down: `$this->deleteAction('batch');`.
However, you'll then find that the list template errors because it assumes that the batch-options dropdown will always be there.
If you don't want to modify the template, you could simply remove the delete option from the dropdown:
`$this->getAction('batch')->getOption('batch_actions')->remove('delete');`


## Adding and removing field actions

You can configure the actions shown next to each item in a list.

The default actions are "edit" and "delete".  To **remove them**, add some code like this to your module configuration file:

    $listAction = $this->getAction("list");
    foreach (array('edit', 'delete') as $key) {
        $listAction->getOption('model_actions')->remove($key);
    }

You can also add **new actions**.  First, create a template file with the necessary HTML or Twig in.  In this example, we're adding a link to a list of clients which goes to a list page for users belonging to that client.

    <a href="{{ path('admin_clientusers_list', { 'client_id': model.id }) }}">
        <i class="icon-list"></i>
        Client users
    </a>

(Notice that the route has an attribute which is the client's ID, retrieved via a reference to the `model` variable.  `model` is the item being administered; in this instance, the item whose list entry is being created.)

Then reference that template file in your module configuration like this:

    $listAction = $this->getAction("list");
    $listAction->getOption('model_actions')->add(array(
        'clientUsersList' => 'path/to/your/new/template/file.html.twig',
    ));

## Redirecting to a different page after creating/editing/deleting

You can alter the page to which a user is taken after creating/editing/deleting by setting the `redirection_url` option for the "create", "update" or "delete" action as appropriate.

At its simplest, this can be a string:

    $createAction = $this->getAction("create");
    $createAction->setOption("redirection_url", "http://www.google.com");

You can also pass in a PHP callable which returns a URL string.  If you're passing a callable as the `redirection_url` option for the "create" or "update" action, the callable will be passed a parameter when called.  This parameter is the entity that has just been created or updated:

    $redirectFunc = function($model) use ($router) {
        return $router->generate('my_route', array('user' => $model->getId()));
    };

    $createAction = $this->getAction("create");
    $createAction->setOption("redirection_url", $redirectFunc);

If you don't set the `redirection_url` option, the user will be redirected to the appropriate entity's list page.

## Custom templates

You can use a custom Twig file for any of your templates if necessary.
Simply retrieve the relevant action and set its `'template'` option:

For lists, for example:

    $listAction = $listAction = $this->getAction("list");
    $listAction->setOption('template', 'YourBundle:Admin:list.html.twig');

(For the create form, retrieve the `new` action and for the edit form, retrieve the `edit` action.)

### Templates for list items

You can also change the template that is used to display items in your lists.
There are some default template types in `Resources/views/fields`, or you can create your own.

Consider the example where an entity has an associated "creator" (a user object) and you want the
creator's e-mail address to be displayed in the list.

You'd start by creating a basic template file; let's call it `creator.html.twig`:

    {% if model.Creator is not null %}
        {{ model.Creator.email }}
    {% endif %}

And then you can reference that template when setting up your model fields in your module:

    $modelFields = $this->getOption("model_fields");
    $modelFields->add([
        'creator' => [
            'label' => 'Creator',
            'template' => 'YourProjAdminBundle::fields/creator.html.twig',
        ],
    ]);


### Passing additional parameters into templates

You have three options:

1. For the values of things used in the route (e.g. IDs), use `addParameterToPropagate` and retrieve with `app.request.attributes.get('blah')`.

2. For passing in more complex objects, use a [module option](https://github.com/whiteoctober/PablodipModuleBundle#options).  You are limited by the data available to the module in terms of what you can pass in.

3. For ultimate flexibility, define a custom route action.

_Previous: [Basic Usage](basic-usage.md)_

_Next: [Filters](filters.md)_

_Back to [README.md](../README.md)_
