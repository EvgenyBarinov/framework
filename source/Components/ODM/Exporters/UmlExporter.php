<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Components\ODM\Exporters;

use Spiral\Components\Files\FileManager;
use Spiral\Components\ODM\Document;
use Spiral\Components\ODM\ODM;
use Spiral\Components\ODM\SchemaBuilder;
use Spiral\Components\ODM\Schemas\DocumentSchema;
use Spiral\Core\Component;
use Spiral\Support\Generators\Reactor\BaseElement;

class UmlExporter extends Component
{
    /**
     * Indent value to use on different UML levels, 4 spaces as usual.
     */
    const indent = '  ';

    /**
     * UML labels to highlight access levels.
     *
     * @var array
     */
    protected $access = [
        BaseElement::ACCESS_PUBLIC    => '+',
        BaseElement::ACCESS_PRIVATE   => '-',
        BaseElement::ACCESS_PROTECTED => '#'
    ];

    /**
     * We are going to generate UML by lines.
     *
     * @var array
     */
    protected $lines = [];

    /**
     * ODM documents schema.
     *
     * @var SchemaBuilder
     */
    protected $builder = null;

    /**
     * FileManager component.
     *
     * @var FileManager
     */
    protected $file = null;

    /**
     * New instance of UML ODM exporter.
     *
     * @param SchemaBuilder $builder
     * @param FileManager   $file
     */
    public function __construct(SchemaBuilder $builder, FileManager $file)
    {
        $this->builder = $builder;
        $this->file = $file;
    }

    /**
     * Render UML classes diagram to specified file, all found Documents with their fields, methods
     * and compositions will be used to generate such UML.
     *
     * @param string $filename
     * @return bool
     */
    public function render($filename)
    {
        $this->line('@startuml');

        foreach ($this->builder->getDocumentSchemas() as $document)
        {
            $this->renderDocument($document);
        }

        $this->line('@enduml');

        return $this->file->write($filename, join("\n", $this->lines));
    }

    /**
     * Add new line to UML diagram with specified indent.
     *
     * @param string $line
     * @param int    $indent
     * @return $this
     */
    protected function line($line, $indent = 0)
    {
        $this->lines[] = str_repeat(self::indent, $indent) . $line;

        return $this;
    }

    /**
     * Normalize class name to show it correctly in UML data.
     *
     * @param string $class
     * @return string
     */
    protected function normalizeName($class)
    {
        return '"' . addslashes($class) . '"';
    }

    /**
     * Adding document (class) to UML.
     *
     * @param DocumentSchema $document
     */
    protected function renderDocument(DocumentSchema $document)
    {
        $parentDocument = null;
        if ($document->getParent())
        {
            $parentDocument = $this->builder->getDocument($document->getParent());
        }

        $className = $this->normalizeName($document->getClass());

        if ($document->isAbstract())
        {
            $this->line("abstract class $className { ");
        }
        else
        {
            $this->line("class $className { ");
        }

        //Document fields
        foreach ($document->getFields() as $field => $type)
        {
            if (is_array($type))
            {
                $type = $type[0] . '[]';
            }

            $this->line(
                $this->access[BaseElement::ACCESS_PUBLIC] . ' ' . addslashes($type) . ' ' . $field,
                1
            );
        }

        //Methods
        foreach ($document->getMethods() as $method)
        {
            $parameters = [];
            foreach ($method->getParameters() as $parameter)
            {
                $parameters[] = ($parameter->getType() ? $parameter->getType() . ' ' : '')
                    . $parameter->getName();
            }

            $this->line(
                $this->access[$method->getAccess()] . ' '
                . $method->getReturn() . ' '
                . $method->getName() . '(' . join(', ', $parameters) . ')',
                1
            );
        }

        $this->line('}')->line('');

        //Parent class
        if ($parentDocument)
        {
            $this->line(
                "$className --|> " . $this->normalizeName($parentDocument->getClass())
            )->line('');
        }

        foreach ($document->getCompositions() as $name => $composition)
        {
            //Show connections only for parent document
            if ($parentDocument && isset($parentDocument->getCompositions()[$name]))
            {
                continue;
            }

            if ($composition['type'] == ODM::CMP_MANY)
            {
                $this->line(
                    "$className ..*" . $this->normalizeName($composition['class']) . ":$name"
                )->line('');
            }
            else
            {
                $this->line(
                    "$className --* " . $this->normalizeName($composition['class']) . ":$name"
                )->line('');
            }
        }

        foreach ($document->getAggregations() as $name => $aggregation)
        {
            //Show connections only for parent document
            if ($parentDocument && isset($parentDocument->getAggregations()[$name]))
            {
                continue;
            }

            if ($aggregation['type'] == Document::MANY)
            {
                $this->line(
                    "$className ..o " . $this->normalizeName($aggregation['class']) . ":$name"
                )->line('');
            }
            else
            {
                $this->line(
                    "$className --o " . $this->normalizeName($aggregation['class']) . ":$name"
                )->line('');
            }
        }
    }
}