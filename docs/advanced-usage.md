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

## Custom form fields

By default, the module will render you textboxes, checkboxes and drop-downs for dates, all worked out from the value you set for `template`.  However, you can instruct it to use any of [Symfony2's form types](http://symfony.com/doc/current/reference/forms/types.html) thanks to the `form_type` property!  You can also pass through settings for these types.  Here's an example:

    $modelFields->add(array(
        'votesPercent' => array(
            'label' => 'votes_percent',
            'form_type' => 'percent',
            'template' => 'PablodipAdminModuleBundle::fields/text.html.twig',
            'form_options' => array('required' => false, 'type' => 'integer')
        ),
    ));

## Custom field actions

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

## Validation groups

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

## Parent/child relationships

Say that you have an admin module for a Region, and each of those Region has one or more Countries.  In such a "parent/child" situation, when the admin bundle creates you new Countries, it needs to set the details of their parent Region.  Fortunately, you can use the _MolinoNestedExtension_ to handle a lot of this for you!  This section explains how.

### Step 1: Create your child module PHP file as normal

The only difference is that you'll have some sort of references entry for the child object, relating it back to its parent.

In Doctrine, for example, you might have this in your `Country` class definition:

    /**
     * @ORM\ManyToOne(targetEntity="Region", inversedBy="countries")
     * @var integer
     */
    protected $region;

A Mandango schema might look like this:

    Model\MyProjCoreBundle\Country:
       fields:
           language: string
           timezone: string
       referencesOne:
           region: { class: Model\MyProjCoreBundle\Region, reference: region }

This sort of thing isn't AdminBundle-specific, but the later steps are.

### Step 2: Set up MolinoNestedExtension

Add some code similar to the following into your child module's class:

    protected function registerExtensions()
    {
        $extensions = parent::registerExtensions();
        $extensions[] = new MolinoNestedExtension(array(
            'parent_class'      => "Model\\MyProjCoreBundle\\Region",
            'route_parameter'   => "programme_id",
            'query_field'       => "programme",
            'association'       => "programme",
        ));

        return $extensions;
    }

You can read more about extensions in general at https://github.com/whiteoctober/PablodipModuleBundle#extensions

If you look in the MolinoNestedExtension code, you can see that it does various things:

1. Before the child controller executes, the extension looks up the parent in the database and makes the parent available as an attribute on the request (see `addCheckParentControllerPreExecute`).  NB You can't retrieve this value in the Module's `configure` function - it won't be available at that point.
2. When queries are run for the child object, [Molino events](https://github.com/whiteoctober/molino#events) are used to add a criteria ensuring that the parent is matched too (`addCreateQueryEvent`).
3. Similarly, when the child object is saved, the parent reference is automatically set (see `addCreateModelEvent`).  This uses the `association` property you've set up in `registerExtensions` - it calls a method called `set<Association>`, where `<Association>` is the value set for `association` with the first letter capitalised.

You'll also need to add some Molino config into your module.  Something like this:

    protected function registerMolinoExtension()
    {
        $eventDispatcher = $this->registerMolinoEventDispatcher();

        return new DoctrineORMMolinoExtension($eventDispatcher);
    }

    protected function registerMolinoEventDispatcher()
    {
        return new EventDispatcher();
    }

### Step 3: Set up route

You'll notice that one of the objects passed to the MolinoNestedExtension constructor is a route parameter.  This allows the extension to work out which parent object the child object belongs to by looking at the route.

Therefore, you'll need to ensure that your child object's route prefix is set up appropriately.  Continuing the example above, you might use:

    $this->setRoutePatternPrefix('/admin/regions/{region_id}/countries');

### Step 4: Modify templates

To actually make use of this functionality, you'll need to give some way of accessing the appropriate new/edit pages for your child objects, and some way of listing them.  Exactly how you do this is up to you; this section shows one way to do it

For creating new objects, you could add the following link to the parent's template, inside the loop which generates code for each parent object:

    <a href="{{ path('admin_countries_new', { 'region_id': model.id } ) }}">{% trans %}add_country{% endtrans %}</a>

The `trans` tags are only required if your application does localisation (which it probably should!)

For listing and editing the child objects, you could create a separate controller method which renders a template within the parent template:

    {% render "MyProjAdminBundle:Default:countryList" with {'region_id': model.id} %}

That template could then include edit links in a similar way to the new link shown above.

#### Referring to other fields on the parent

In the template examples above, we simply pulled in the `region_id` into the template.  If you want access to other properties of the parent object, that is possible using the special `_parent` attribute.  For example, consider the following code which prints out the parent object's name:

    {{ app.request.attributes.get('_parent').name }}

### Multiple levels of nesting

Multiple levels of nesting are supported.  In this sort of context, you might have a route like this:

    $this->setRoutePatternPrefix('/regions/{region_id}/countries/{country_id}/district')

One of the parameters here will be used when registering the Molino nested extension.  In order for the other parameter to work, you'll need to call `addParameterToPropagate` when setting up the routing in your `configure` method.  E.g:

    $this->addParameterToPropagate('region_id');

This only needs doing for the parameter which isn't mentioned in the MolinoNestedExtension constructor.

`addParameterToPropagate` can also be used for passing other parameters into your templates.  You can then retrieve them with `app.request.attributes.get('blah')`

If you need to pass in something more complicated, use a [module option](https://github.com/whiteoctober/PablodipModuleBundle#options).

## Passing additional parameters into templates

You have three options:

1. For the values of things used in the route (e.g. IDs), use `addParameterToPropagate` and retrieve with `app.request.attributes.get('blah')`.

2. For passing in more complex objects, use a [module option](https://github.com/whiteoctober/PablodipModuleBundle#options).  You are limited by the data available to the module in terms of what you can pass in.

3. For ultimate flexibility, define a custom route action.

_Previous: [Basic Usage](basic-usage.md)_

_Next: [Filters](filters.md)_

_Back to [README.md](../README.md)_
