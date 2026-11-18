<?php

declare(strict_types=1);

namespace GraftcodeProtocol\Enums;

enum UnifiedGraftModelType: int
{
    case LIBRARY = 0;
    case STATIC_METHOD = 1;
    case STATIC_FIELD = 2;
    case CONSTRUCTOR = 3;
    case TYPE_DEF = 4;
    case MODULE = 5;
    case INSTANCE_METHOD = 6;
    case CAST = 7;
    case INSTANCE_FIELD = 8;
    case GLOBAL_METHOD = 9;
    case DESTRUCTOR = 10;
    case GENERIC_STATIC_METHOD = 11;
    case GENERIC_INSTANCE_METHOD = 12;
    case ENUM = 13;
    case REF = 14;
    case OUT = 15;
    case ARRAY = 16;
    case PARAMETERS_ARRAY = 17;
    case ARGUMENT_NAME = 18;
    case CLASS_NAME = 19;
    case METHOD_NAME = 20;
    case MODIFIER = 21;
    case TYPE_USAGE = 22;
    case PARAMETER = 23;
    case NAMESPACE = 24;
    case MODULE_FULL_NAME = 25;
    case MODULE_HASH = 26;
    case METHOD_BODY = 27;
    case CLAZZ_INSTANCE = 28;
    case RETURN_TYPE = 29;
    case GLOBAL_FIELD = 30;
    case GLOBAL_TYPE = 31;
    case FIELD_NAME = 32;
    case PROPERTY = 33;
    case SETTER = 34;
    case GETTER = 35;
    case GENERIC_TYPE_DEF = 36;
    case OVERLOADED_CONSTRUCTOR = 37;
    case OVERLOADED_STATIC_METHOD = 38;
    case OVERLOADED_INSTANCE_METHOD = 39;
    case OVERLOADED_GLOBAL_METHOD = 40;
    case TYPE_USAGE_VOID = 41;
    case TYPE_USAGE_ARRAY = 42;
    case TYPE_USAGE_PRIMITIVE = 43;
    case TYPE_USAGE_GENERIC = 44;
    case TYPE_USAGE_GENERIC_ALIAS = 45;
    case GENERIC_PARAMETERS = 46;
    case SET_INSTANCE_FIELD = 47;
    case SET_STATIC_FIELD = 48;
    case TYPE_INHERITANCE = 49;
    case TYPE_INHERITANCE_ENTRY = 50;

    // ------------------------------------------------------------------
    // CommentDoc (UGM v1.4.0) - structured documentation comments.
    // Neutral container for Javadoc / XML doc / JSDoc / Python docstring /
    // PHPDoc / YARD-RDoc metadata. COMMENT_DOC is appended at the END of
    // payload of methods/constructors; consumers must look it up by type,
    // not by index, to stay backward-compatible with older payload
    // positional contracts. Ordinals MUST stay aligned across runtimes
    // (51..67).
    // ------------------------------------------------------------------
    case COMMENT_DOC = 51;
    case COMMENT_SUMMARY = 52;
    case COMMENT_DESCRIPTION = 53;
    case COMMENT_PARAMETERS_ARRAY = 54;
    case COMMENT_PARAMETER = 55;
    case COMMENT_RETURNS = 56;
    case COMMENT_THROWS_ARRAY = 57;
    case COMMENT_THROW = 58;
    case COMMENT_EXAMPLES_ARRAY = 59;
    case COMMENT_EXAMPLE = 60;
    case COMMENT_LINKS_ARRAY = 61;
    case COMMENT_LINK = 62;
    case COMMENT_DEPRECATED = 63;
    case COMMENT_NOTES_ARRAY = 64;
    case COMMENT_WARNINGS_ARRAY = 65;
    case COMMENT_METADATA = 66;
    case COMMENT_SOURCE = 67;
    case TYPE_DEF_KIND = 68;
    case ABSTRACT_INSTANCE_METHOD = 69;
    case ABSTRACT_STATIC_METHOD = 70;
    case READONLY_SETTER = 71;
    case METHOD_COMPLETION = 72;
}

