# Filters (advanced search)

Want to offer a standard filter interface to your admin users? Easy peasy.  First make sure any fields that you want to filter on have been added in your admin module in the normal way via the `model_fields` option, then follow the instructions in this section:

## Choose or create a filter class, and configure your fields

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

### GOTCHA: "is not" and null columns

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

## Configure your Action

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

_Previous: [Advanced Usage](advanced-usage.md)_

_Next: [Administering parent/child entity relationships](docs/parent-child.md)_

_Back to [README.md](../README.md)_
