<?php

declare(strict_types=1);

namespace Protocol;

use GraftcodeProtocol\Enums\RuntimeName;
use GraftcodeProtocol\Enums\UnifiedGraftModelType;
use GraftcodeProtocol\Models\UnifiedGraftModel;
use GraftcodeProtocol\Protocol\Deserializers\UnifiedGraftModelDeserializer;
use GraftcodeProtocol\Protocol\Serializers\UnifiedGraftModelSerializer;
use PHPUnit\Framework\{
    Attributes\Group,
    TestCase
};
use Tests\DataSeed\ProtocolDataSeed;

final class UnifiedGraftModelTest extends TestCase
{
    #[Group('unit')]
    public function test_nestedRubyCommand_Serialize_Deserialize(): void
    {
        $serialized = UnifiedGraftModelSerializer::serialize(
            ProtocolDataSeed::getNestedRubyCommand()
        );

        $this->assertIsArray($serialized);
        $this->assertEquals(
            ProtocolDataSeed::getNestedRubyCommand(),
            new UnifiedGraftModelDeserializer($serialized)->deserialize()
        );
    }

    #[Group('unit')]
    public function test_nestedPhpCommand_Serialize_Deserialize(): void
    {
        $serialized = UnifiedGraftModelSerializer::serialize(
            ProtocolDataSeed::getNestedPhpCommand()
        );

        $this->assertIsArray($serialized);
        $this->assertEquals(
            ProtocolDataSeed::getNestedPhpCommand(),
            new UnifiedGraftModelDeserializer($serialized)->deserialize()
        );
    }

    #[Group('unit')]
    public function test_nestedJavaCommand_Serialize_Deserialize(): void
    {
        $serialized = UnifiedGraftModelSerializer::serialize(
            ProtocolDataSeed::getNestedCommandJava()
        );

        $this->assertIsArray($serialized);
        $this->assertEquals(
            ProtocolDataSeed::getNestedCommandJava(),
            new UnifiedGraftModelDeserializer($serialized)->deserialize()
        );
    }

    /**
     * Round-trip test for the CommentDoc UGM subtree (UGM v1.4.0).
     *
     * The serializer / deserializer pair is type-agnostic - it only knows about
     * UnifiedGraftModel nodes and primitive payload entries. Adding new
     * UnifiedGraftModelType values therefore must not require any changes to
     * the serializer logic; this test asserts that property by building a
     * fully populated COMMENT_DOC tree, serializing it, deserializing it back,
     * and asserting structural equality.
     */
    #[Group('unit')]
    public function test_fullCommentDoc_Serialize_Deserialize(): void
    {
        $methodNode = self::buildMethodWithCommentDoc(self::buildFullCommentDoc());

        $serialized = UnifiedGraftModelSerializer::serialize($methodNode);

        $this->assertIsArray($serialized);
        $this->assertEquals(
            $methodNode,
            new UnifiedGraftModelDeserializer($serialized)->deserialize()
        );
    }

    #[Group('unit')]
    public function test_minimalCommentDoc_Serialize_Deserialize(): void
    {
        $methodNode = self::buildMethodWithCommentDoc(
            self::ugm(UnifiedGraftModelType::COMMENT_DOC, [])
        );

        $serialized = UnifiedGraftModelSerializer::serialize($methodNode);

        $this->assertEquals(
            $methodNode,
            new UnifiedGraftModelDeserializer($serialized)->deserialize()
        );
    }

    #[Group('unit')]
    public function test_summaryOnlyCommentDoc_Serialize_Deserialize(): void
    {
        $ctorNode = new UnifiedGraftModel(
            RuntimeName::PHP,
            UnifiedGraftModelType::CONSTRUCTOR,
            'MyClass',
            0,
            self::ugm(UnifiedGraftModelType::PARAMETERS_ARRAY, []),
            self::ugm(UnifiedGraftModelType::COMMENT_DOC, [
                self::ugm(UnifiedGraftModelType::COMMENT_SUMMARY, ['Short summary.']),
            ]),
        );

        $serialized = UnifiedGraftModelSerializer::serialize($ctorNode);

        $this->assertEquals(
            $ctorNode,
            new UnifiedGraftModelDeserializer($serialized)->deserialize()
        );
    }

    private static function ugm(UnifiedGraftModelType $type, array $payload): UnifiedGraftModel
    {
        return new UnifiedGraftModel(RuntimeName::PHP, $type, $payload);
    }

    private static function buildMethodWithCommentDoc(UnifiedGraftModel $commentDoc): UnifiedGraftModel
    {
        return new UnifiedGraftModel(
            RuntimeName::PHP,
            UnifiedGraftModelType::INSTANCE_METHOD,
            'issueInvoice',
            self::ugm(UnifiedGraftModelType::TYPE_USAGE, []),
            0,
            self::ugm(UnifiedGraftModelType::PARAMETERS_ARRAY, []),
            $commentDoc,
        );
    }

    private static function buildFullCommentDoc(): UnifiedGraftModel
    {
        $summary = self::ugm(UnifiedGraftModelType::COMMENT_SUMMARY, ['Issue a new invoice.']);
        $description = self::ugm(UnifiedGraftModelType::COMMENT_DESCRIPTION, [
            'Validates input and persists the invoice.',
        ]);

        $paramCustomer = self::ugm(UnifiedGraftModelType::COMMENT_PARAMETER, [
            'customerId', 'int', 'integer', 'Customer identifier.', true, false, '',
        ]);
        $paramAmount = self::ugm(UnifiedGraftModelType::COMMENT_PARAMETER, [
            'amount', 'float', 'decimal', 'Total amount in major units.', true, false, '',
        ]);
        $parameters = self::ugm(UnifiedGraftModelType::COMMENT_PARAMETERS_ARRAY, [
            $paramCustomer, $paramAmount,
        ]);

        $returns = self::ugm(UnifiedGraftModelType::COMMENT_RETURNS, [
            'array', 'map', 'Invoice record.', false,
        ]);

        $throwsArr = self::ugm(UnifiedGraftModelType::COMMENT_THROWS_ARRAY, [
            self::ugm(UnifiedGraftModelType::COMMENT_THROW, [
                'InvalidArgumentException', 'error', 'When amount is not positive.',
            ]),
        ]);

        $examples = self::ugm(UnifiedGraftModelType::COMMENT_EXAMPLES_ARRAY, [
            self::ugm(UnifiedGraftModelType::COMMENT_EXAMPLE, [
                'Basic usage', 'php', '$service->issueInvoice(42, 10.0);',
            ]),
        ]);

        $links = self::ugm(UnifiedGraftModelType::COMMENT_LINKS_ARRAY, [
            self::ugm(UnifiedGraftModelType::COMMENT_LINK, ['Customer', 'Customer', 'see']),
        ]);

        $deprecated = self::ugm(UnifiedGraftModelType::COMMENT_DEPRECATED, [false, '', '']);

        $notes = self::ugm(UnifiedGraftModelType::COMMENT_NOTES_ARRAY, [
            'Persists the invoice in the billing database.',
        ]);
        $warnings = self::ugm(UnifiedGraftModelType::COMMENT_WARNINGS_ARRAY, [
            'Do not call from a transaction.',
        ]);

        $metadata = self::ugm(UnifiedGraftModelType::COMMENT_METADATA, [
            'Jane Doe', '1.0.0', '1.2.0', 'MIT', 'billing,invoices',
        ]);

        $source = self::ugm(UnifiedGraftModelType::COMMENT_SOURCE, [
            'php', 'InvoiceService.php', 15, 42, 'phpdoc',
            "/**\n * Issue a new invoice.\n */",
        ]);

        return self::ugm(UnifiedGraftModelType::COMMENT_DOC, [
            $summary, $description, $parameters, $returns, $throwsArr,
            $examples, $links, $deprecated, $notes, $warnings, $metadata, $source,
        ]);
    }
}
