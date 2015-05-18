# Administering parent/child entity relationships

Say that you have an admin module for a Region, and each of those Region has one or more Countries.  In such a "parent/child" situation, when the admin bundle creates you new Countries, it needs to set the details of their parent Region.  Fortunately, you can use the _MolinoNestedExtension_ to handle a lot of this for you!  This section explains how.

## Step 1: Create your child module PHP file as normal

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

## Step 2: Set up MolinoNestedExtension

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

## Step 3: Set up route

You'll notice that one of the objects passed to the MolinoNestedExtension constructor is a route parameter.  This allows the extension to work out which parent object the child object belongs to by looking at the route.

Therefore, you'll need to ensure that your child object's route prefix is set up appropriately.  Continuing the example above, you might use:

    $this->setRoutePatternPrefix('/admin/regions/{region_id}/countries');

## Step 4: Modify templates

To actually make use of this functionality, you'll need to give some way of accessing the appropriate new/edit pages for your child objects, and some way of listing them.  Exactly how you do this is up to you; this section shows one way to do it

For creating new objects, you could add the following link to the parent's template, inside the loop which generates code for each parent object:

    <a href="{{ path('admin_countries_new', { 'region_id': model.id } ) }}">{% trans %}add_country{% endtrans %}</a>

The `trans` tags are only required if your application does localisation (which it probably should!)

For listing and editing the child objects, you could create a separate controller method which renders a template within the parent template:

    {% render "MyProjAdminBundle:Default:countryList" with {'region_id': model.id} %}

That template could then include edit links in a similar way to the new link shown above.

### Referring to other fields on the parent

In the template examples above, we simply pulled in the `region_id` into the template.  If you want access to other properties of the parent object, that is possible using the special `_parent` attribute.  For example, consider the following code which prints out the parent object's name:

    {{ app.request.attributes.get('_parent').name }}

## Multiple levels of nesting

Multiple levels of nesting are supported.  In this sort of context, you might have a route like this:

    $this->setRoutePatternPrefix('/regions/{region_id}/countries/{country_id}/district')

One of the parameters here will be used when registering the Molino nested extension.  In order for the other parameter to work, you'll need to call `addParameterToPropagate` when setting up the routing in your `configure` method.  E.g:

    $this->addParameterToPropagate('region_id');

This only needs doing for the parameter which isn't mentioned in the MolinoNestedExtension constructor.

`addParameterToPropagate` can also be used for passing other parameters into your templates.  You can then retrieve them with `app.request.attributes.get('blah')`

If you need to pass in something more complicated, use a [module option](https://github.com/whiteoctober/PablodipModuleBundle#options).

_Previous: [Filters](filters.md)_

_Next: [Troubleshooting](troubleshooting.md)_

_Back to [README.md](../README.md)_
