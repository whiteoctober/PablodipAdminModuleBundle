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

The easiest way to sort by default is to configure the route-creation code to include the necessary parameters.  In Twig, for example, you might have some code like this:

    <a href="{{ path("admin_user_list", {'sort': 'lastName', 'order': 'asc'}) }}">User list</a>

`sort` and `order` will be added as querystring parameters and picked up by the module.

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

## Filters (advanced search)

Want to offer a standard filter interface to your admin users? Easy peasy.  First make sure any fields that you want to filter on have been added in your admin module in the normal way via the `model_fields` option, then follow the instructions in this section:

### Choose or create a filter class, and configure your fields

There are two approaches...

If you want to filter via a normal string `LIKE '%foo%'`-style approach, or a boolean field, add the following into your field definition:

    'advanced_search_type' => 'string',

or

    'advanced_search_type' => 'boolean',

or

    'advanced_search_type' => 'integer',

depending on your requirements.  This will give you a standard set of widgets accessible via the Advanced Search container on the default list page.

Alternatively, if you want to add some kind of custom filter (eg a date filter, a dropdown populated by your own special items and so on), you'll need to create a custom filter class, which extends from `Pablodip\AdminModuleBundle\Filter\BaseFilter`.  This has 3 methods that will need to be implemented - see the base filters for an example.  Essentially you're just creating a form, and then applying any values submitted to a supplied Molino Query object to perform the filtering.

You then use the custom filter by using the `advanced_search_filter` property in your field definition:

    'advanced_search_filter' => new Filter\MySpecialCustomFilter($this->getContainer()->get('translator')), // BaseFilter's constructor requires a TranslatorInterface to be passed in

#### GOTCHA: "is not" and null columns

In the course of writing your filters, you'll probably include some code like this:

        if ($data['type'] == 'is') {
            $query->filterEqual('column-name-here', $data['value']);
        }
        if ($data['type'] == 'is not') {
            $query->filterNotEqual('column-name-here', $data['value']);
        }

In many cases, this will do what you want.  However, bear in mind cases like the following example:

1. You have a nullable column called "payment method"
2. 8 entities have this as null, 1 has this set to "Credit Card" and 1 has this set to "Debit Card"
3. You filter "Payment method is not Credit Card"

How many results should be returned?  The simple code above will return 1 result, that with a payment method of "Debit Card".  If you want nulls to match too, you'll need to write some more complicated code for your `filter` method.  It'll look something like this:

    use Doctrine\ORM\Query\Expr\Andx;
    use Doctrine\ORM\Query\Expr\Orx;
    use Doctrine\ORM\QueryBuilder;
    use Molino\QueryInterface;
    use Pablodip\AdminModuleBundle\Filter\BaseFilter;

    // ...

    public function filter(QueryInterface $query, $fieldName, array $data)
    {
        if ($data['type'] == 'is') {
            $query->filterEqual('paymentMethod', $data['value']);
        }
        if ($data['type'] == 'is not') {
            // Have to build a more complex queries, so that null counts as "not equal to anything"
            // Doctrine's SelectQuery has the getQueryBuilder method (from BaseQuery), even though the QueryInterface doesn't
            /* @var $qb QueryBuilder */
            $qb = $query->getQueryBuilder();

            // any existing conditions?
            $existingWhere = $qb->getDQLPart('where');
            if ($existingWhere) {
                $existingWhere = $existingWhere->getParts();
            }

            // get table aliases so can associate the column with a table
            $aliases = $qb->getRootAliases();
            $colNameAndIdentifier = $aliases[0] . '.paymentMethod';

            // WHERE [any existing conditions] AND (paymentMethod <> ?x OR paymentMethod IS NULL)
            $or = new Orx(
                array(
                    $qb->expr()->neq($colNameAndIdentifier, ':paymentMethod'),
                    $qb->expr()->isNull($colNameAndIdentifier)
                )
            );

            if ($existingWhere) {
                $conditions = new Andx(
                    $existingWhere
                );
                $conditions->add($or);
            } else {
                $conditions = $or;
            }
            $qb->add('where', $conditions);

            $qb->setParameter('paymentMethod', $data['value']);
        }
    }

### Configure your Action

Once you've configured your fields, the final step is to tell your `ListAction` that you want to add fields to the search filter.  This is done via the action's `advanced_search_fields` option (shown here being configured within a module):

    $listAction = $this->getAction('list');
    $modelFields = $this->getOption('model_fields');
    $listAction->getOption('advanced_search_fields')->add(array(
        ...,
        'yourFieldName' => $modelFields->get('yourFieldName'),
        'anotherField' => $modelFields->get('anotherField'),
        ...,
    ));

You'll be able to filter now from the list page in your module.

_Previous: [Basic Usage](basic-usage.md)_

_Next: [Troubleshooting](troubleshooting.md)_

_Back to [README.md](../README.md)_
