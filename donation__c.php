<?php // Donation__c
stdClass Object
(
    [activateable] => 
    [childRelationships] => Array
        (
            [0] => stdClass Object
                (
                    [cascadeDelete] => 1
                    [childSObject] => ActivityHistory
                    [deprecatedAndHidden] => 
                    [field] => WhatId
                    [relationshipName] => ActivityHistories
                )

            [1] => stdClass Object
                (
                    [cascadeDelete] => 1
                    [childSObject] => Attachment
                    [deprecatedAndHidden] => 
                    [field] => ParentId
                    [relationshipName] => Attachments
                )

            [2] => stdClass Object
                (
                    [cascadeDelete] => 1
                    [childSObject] => ContentDocumentLink
                    [deprecatedAndHidden] => 
                    [field] => LinkedEntityId
                )

            [3] => stdClass Object
                (
                    [cascadeDelete] => 
                    [childSObject] => ContentVersion
                    [deprecatedAndHidden] => 
                    [field] => FirstPublishLocationId
                )

            [4] => stdClass Object
                (
                    [cascadeDelete] => 1
                    [childSObject] => Donation__History
                    [deprecatedAndHidden] => 
                    [field] => ParentId
                    [relationshipName] => Histories
                )

            [5] => stdClass Object
                (
                    [cascadeDelete] => 1
                    [childSObject] => Donation__Tag
                    [deprecatedAndHidden] => 
                    [field] => ItemId
                    [relationshipName] => Tags
                )

            [6] => stdClass Object
                (
                    [cascadeDelete] => 1
                    [childSObject] => EntitySubscription
                    [deprecatedAndHidden] => 
                    [field] => ParentId
                    [relationshipName] => FeedSubscriptionsForEntity
                )

            [7] => stdClass Object
                (
                    [cascadeDelete] => 1
                    [childSObject] => Event
                    [deprecatedAndHidden] => 
                    [field] => WhatId
                    [relationshipName] => Events
                )

            [8] => stdClass Object
                (
                    [cascadeDelete] => 
                    [childSObject] => FeedComment
                    [deprecatedAndHidden] => 
                    [field] => ParentId
                )

            [9] => stdClass Object
                (
                    [cascadeDelete] => 1
                    [childSObject] => FeedItem
                    [deprecatedAndHidden] => 
                    [field] => ParentId
                )

            [10] => stdClass Object
                (
                    [cascadeDelete] => 1
                    [childSObject] => Note
                    [deprecatedAndHidden] => 
                    [field] => ParentId
                    [relationshipName] => Notes
                )

            [11] => stdClass Object
                (
                    [cascadeDelete] => 1
                    [childSObject] => NoteAndAttachment
                    [deprecatedAndHidden] => 
                    [field] => ParentId
                    [relationshipName] => NotesAndAttachments
                )

            [12] => stdClass Object
                (
                    [cascadeDelete] => 1
                    [childSObject] => OpenActivity
                    [deprecatedAndHidden] => 
                    [field] => WhatId
                    [relationshipName] => OpenActivities
                )

            [13] => stdClass Object
                (
                    [cascadeDelete] => 1
                    [childSObject] => ProcessInstance
                    [deprecatedAndHidden] => 
                    [field] => TargetObjectId
                    [relationshipName] => ProcessInstances
                )

            [14] => stdClass Object
                (
                    [cascadeDelete] => 
                    [childSObject] => ProcessInstanceHistory
                    [deprecatedAndHidden] => 
                    [field] => TargetObjectId
                    [relationshipName] => ProcessSteps
                )

            [15] => stdClass Object
                (
                    [cascadeDelete] => 1
                    [childSObject] => Task
                    [deprecatedAndHidden] => 
                    [field] => WhatId
                    [relationshipName] => Tasks
                )

            [16] => stdClass Object
                (
                    [cascadeDelete] => 1
                    [childSObject] => TopicAssignment
                    [deprecatedAndHidden] => 
                    [field] => EntityId
                )

        )

    [createable] => 1
    [custom] => 1
    [customSetting] => 
    [deletable] => 1
    [deprecatedAndHidden] => 
    [feedEnabled] => 
    [fields] => Array
        (
            [0] => stdClass Object
                (
                    [autoNumber] => 
                    [byteLength] => 18
                    [calculated] => 
                    [caseSensitive] => 
                    [createable] => 
                    [custom] => 
                    [defaultedOnCreate] => 1
                    [deprecatedAndHidden] => 
                    [digits] => 0
                    [filterable] => 1
                    [groupable] => 1
                    [idLookup] => 1
                    [label] => Record ID
                    [length] => 18
                    [name] => Id
                    [nameField] => 
                    [namePointing] => 
                    [nillable] => 
                    [permissionable] => 
                    [precision] => 0
                    [restrictedPicklist] => 
                    [scale] => 0
                    [soapType] => tns:ID
                    [sortable] => 1
                    [type] => id
                    [unique] => 
                    [updateable] => 
                )

            [1] => stdClass Object
                (
                    [autoNumber] => 
                    [byteLength] => 0
                    [calculated] => 
                    [caseSensitive] => 
                    [createable] => 
                    [custom] => 
                    [defaultedOnCreate] => 1
                    [deprecatedAndHidden] => 
                    [digits] => 0
                    [filterable] => 1
                    [groupable] => 1
                    [idLookup] => 
                    [label] => Deleted
                    [length] => 0
                    [name] => IsDeleted
                    [nameField] => 
                    [namePointing] => 
                    [nillable] => 
                    [permissionable] => 
                    [precision] => 0
                    [restrictedPicklist] => 
                    [scale] => 0
                    [soapType] => xsd:boolean
                    [sortable] => 1
                    [type] => boolean
                    [unique] => 
                    [updateable] => 
                )

            [2] => stdClass Object
                (
                    [autoNumber] => 1
                    [byteLength] => 240
                    [calculated] => 
                    [caseSensitive] => 
                    [createable] => 
                    [custom] => 
                    [defaultedOnCreate] => 1
                    [deprecatedAndHidden] => 
                    [digits] => 0
                    [filterable] => 1
                    [groupable] => 
                    [idLookup] => 1
                    [label] => Donations #
                    [length] => 80
                    [name] => Name
                    [nameField] => 1
                    [namePointing] => 
                    [nillable] => 
                    [permissionable] => 
                    [precision] => 0
                    [restrictedPicklist] => 
                    [scale] => 0
                    [soapType] => xsd:string
                    [sortable] => 1
                    [type] => string
                    [unique] => 
                    [updateable] => 
                )

            [3] => stdClass Object
                (
                    [autoNumber] => 
                    [byteLength] => 0
                    [calculated] => 
                    [caseSensitive] => 
                    [createable] => 
                    [custom] => 
                    [defaultedOnCreate] => 1
                    [deprecatedAndHidden] => 
                    [digits] => 0
                    [filterable] => 1
                    [groupable] => 
                    [idLookup] => 
                    [label] => Created Date
                    [length] => 0
                    [name] => CreatedDate
                    [nameField] => 
                    [namePointing] => 
                    [nillable] => 
                    [permissionable] => 
                    [precision] => 0
                    [restrictedPicklist] => 
                    [scale] => 0
                    [soapType] => xsd:dateTime
                    [sortable] => 1
                    [type] => datetime
                    [unique] => 
                    [updateable] => 
                )

            [4] => stdClass Object
                (
                    [autoNumber] => 
                    [byteLength] => 18
                    [calculated] => 
                    [caseSensitive] => 
                    [createable] => 
                    [custom] => 
                    [defaultedOnCreate] => 1
                    [deprecatedAndHidden] => 
                    [digits] => 0
                    [filterable] => 1
                    [groupable] => 1
                    [idLookup] => 
                    [label] => Created By ID
                    [length] => 18
                    [name] => CreatedById
                    [nameField] => 
                    [namePointing] => 
                    [nillable] => 
                    [permissionable] => 
                    [precision] => 0
                    [referenceTo] => Array
                        (
                            [0] => User
                        )

                    [relationshipName] => CreatedBy
                    [restrictedPicklist] => 
                    [scale] => 0
                    [soapType] => tns:ID
                    [sortable] => 1
                    [type] => reference
                    [unique] => 
                    [updateable] => 
                )

            [5] => stdClass Object
                (
                    [autoNumber] => 
                    [byteLength] => 0
                    [calculated] => 
                    [caseSensitive] => 
                    [createable] => 
                    [custom] => 
                    [defaultedOnCreate] => 1
                    [deprecatedAndHidden] => 
                    [digits] => 0
                    [filterable] => 1
                    [groupable] => 
                    [idLookup] => 
                    [label] => Last Modified Date
                    [length] => 0
                    [name] => LastModifiedDate
                    [nameField] => 
                    [namePointing] => 
                    [nillable] => 
                    [permissionable] => 
                    [precision] => 0
                    [restrictedPicklist] => 
                    [scale] => 0
                    [soapType] => xsd:dateTime
                    [sortable] => 1
                    [type] => datetime
                    [unique] => 
                    [updateable] => 
                )

            [6] => stdClass Object
                (
                    [autoNumber] => 
                    [byteLength] => 18
                    [calculated] => 
                    [caseSensitive] => 
                    [createable] => 
                    [custom] => 
                    [defaultedOnCreate] => 1
                    [deprecatedAndHidden] => 
                    [digits] => 0
                    [filterable] => 1
                    [groupable] => 1
                    [idLookup] => 
                    [label] => Last Modified By ID
                    [length] => 18
                    [name] => LastModifiedById
                    [nameField] => 
                    [namePointing] => 
                    [nillable] => 
                    [permissionable] => 
                    [precision] => 0
                    [referenceTo] => Array
                        (
                            [0] => User
                        )

                    [relationshipName] => LastModifiedBy
                    [restrictedPicklist] => 
                    [scale] => 0
                    [soapType] => tns:ID
                    [sortable] => 1
                    [type] => reference
                    [unique] => 
                    [updateable] => 
                )

            [7] => stdClass Object
                (
                    [autoNumber] => 
                    [byteLength] => 0
                    [calculated] => 
                    [caseSensitive] => 
                    [createable] => 
                    [custom] => 
                    [defaultedOnCreate] => 1
                    [deprecatedAndHidden] => 
                    [digits] => 0
                    [filterable] => 1
                    [groupable] => 
                    [idLookup] => 
                    [label] => System Modstamp
                    [length] => 0
                    [name] => SystemModstamp
                    [nameField] => 
                    [namePointing] => 
                    [nillable] => 
                    [permissionable] => 
                    [precision] => 0
                    [restrictedPicklist] => 
                    [scale] => 0
                    [soapType] => xsd:dateTime
                    [sortable] => 1
                    [type] => datetime
                    [unique] => 
                    [updateable] => 
                )

            [8] => stdClass Object
                (
                    [autoNumber] => 
                    [byteLength] => 0
                    [calculated] => 
                    [caseSensitive] => 
                    [createable] => 
                    [custom] => 
                    [defaultedOnCreate] => 
                    [deprecatedAndHidden] => 
                    [digits] => 0
                    [filterable] => 1
                    [groupable] => 1
                    [idLookup] => 
                    [label] => Last Activity Date
                    [length] => 0
                    [name] => LastActivityDate
                    [nameField] => 
                    [namePointing] => 
                    [nillable] => 1
                    [permissionable] => 
                    [precision] => 0
                    [restrictedPicklist] => 
                    [scale] => 0
                    [soapType] => xsd:date
                    [sortable] => 1
                    [type] => date
                    [unique] => 
                    [updateable] => 
                )

            [9] => stdClass Object
                (
                    [autoNumber] => 
                    [byteLength] => 18
                    [calculated] => 
                    [cascadeDelete] => 1
                    [caseSensitive] => 
                    [createable] => 1
                    [custom] => 1
                    [defaultedOnCreate] => 
                    [deprecatedAndHidden] => 
                    [digits] => 0
                    [filterable] => 1
                    [groupable] => 1
                    [idLookup] => 
                    [label] => Contact
                    [length] => 18
                    [name] => Contact__c
                    [nameField] => 
                    [namePointing] => 
                    [nillable] => 
                    [permissionable] => 
                    [precision] => 0
                    [referenceTo] => Array
                        (
                            [0] => Contact
                        )

                    [relationshipName] => Contact__r
                    [relationshipOrder] => 0
                    [restrictedPicklist] => 
                    [scale] => 0
                    [soapType] => tns:ID
                    [sortable] => 1
                    [type] => reference
                    [unique] => 
                    [updateable] => 
                )

            [10] => stdClass Object
                (
                    [autoNumber] => 
                    [byteLength] => 0
                    [calculated] => 
                    [caseSensitive] => 
                    [createable] => 1
                    [custom] => 1
                    [defaultedOnCreate] => 
                    [deprecatedAndHidden] => 
                    [digits] => 0
                    [filterable] => 1
                    [groupable] => 
                    [idLookup] => 
                    [label] => Amount
                    [length] => 0
                    [name] => Amount__c
                    [nameField] => 
                    [namePointing] => 
                    [nillable] => 1
                    [permissionable] => 1
                    [precision] => 18
                    [restrictedPicklist] => 
                    [scale] => 2
                    [soapType] => xsd:double
                    [sortable] => 1
                    [type] => currency
                    [unique] => 
                    [updateable] => 1
                )

            [11] => stdClass Object
                (
                    [autoNumber] => 
                    [byteLength] => 765
                    [calculated] => 
                    [caseSensitive] => 
                    [createable] => 1
                    [custom] => 1
                    [defaultedOnCreate] => 
                    [deprecatedAndHidden] => 
                    [digits] => 0
                    [filterable] => 1
                    [groupable] => 1
                    [idLookup] => 
                    [label] => Description
                    [length] => 255
                    [name] => Description__c
                    [nameField] => 
                    [namePointing] => 
                    [nillable] => 1
                    [permissionable] => 1
                    [precision] => 0
                    [restrictedPicklist] => 
                    [scale] => 0
                    [soapType] => xsd:string
                    [sortable] => 1
                    [type] => string
                    [unique] => 
                    [updateable] => 1
                )

            [12] => stdClass Object
                (
                    [autoNumber] => 
                    [byteLength] => 0
                    [calculated] => 
                    [caseSensitive] => 
                    [createable] => 1
                    [custom] => 1
                    [defaultedOnCreate] => 
                    [deprecatedAndHidden] => 
                    [digits] => 0
                    [filterable] => 1
                    [groupable] => 1
                    [idLookup] => 
                    [label] => Donation Date
                    [length] => 0
                    [name] => Donation_Date__c
                    [nameField] => 
                    [namePointing] => 
                    [nillable] => 1
                    [permissionable] => 1
                    [precision] => 0
                    [restrictedPicklist] => 
                    [scale] => 0
                    [soapType] => xsd:date
                    [sortable] => 1
                    [type] => date
                    [unique] => 
                    [updateable] => 1
                )

            [13] => stdClass Object
                (
                    [autoNumber] => 
                    [byteLength] => 765
                    [calculated] => 
                    [caseSensitive] => 
                    [createable] => 1
                    [custom] => 1
                    [defaultedOnCreate] => 
                    [deprecatedAndHidden] => 
                    [digits] => 0
                    [filterable] => 1
                    [groupable] => 1
                    [idLookup] => 
                    [label] => Type
                    [length] => 255
                    [name] => Type__c
                    [nameField] => 
                    [namePointing] => 
                    [nillable] => 1
                    [permissionable] => 1
                    [picklistValues] => Array
                        (
                            [0] => stdClass Object
                                (
                                    [active] => 1
                                    [defaultValue] => 
                                    [label] => Cash
                                    [value] => Cash
                                )

                            [1] => stdClass Object
                                (
                                    [active] => 1
                                    [defaultValue] => 
                                    [label] => In-kind
                                    [value] => In-kind
                                )

                            [2] => stdClass Object
                                (
                                    [active] => 1
                                    [defaultValue] => 
                                    [label] => Membership
                                    [value] => Membership
                                )

                            [3] => stdClass Object
                                (
                                    [active] => 1
                                    [defaultValue] => 
                                    [label] => Sponsorship
                                    [value] => Sponsorship
                                )

                            [4] => stdClass Object
                                (
                                    [active] => 1
                                    [defaultValue] => 
                                    [label] => Ticket
                                    [value] => Ticket
                                )

                            [5] => stdClass Object
                                (
                                    [active] => 1
                                    [defaultValue] => 
                                    [label] => Credit Card
                                    [value] => Credit Card
                                )

                        )

                    [precision] => 0
                    [restrictedPicklist] => 
                    [scale] => 0
                    [soapType] => xsd:string
                    [sortable] => 1
                    [type] => picklist
                    [unique] => 
                    [updateable] => 1
                )

        )

    [keyPrefix] => a08
    [label] => Donation
    [labelPlural] => Donations
    [layoutable] => 1
    [mergeable] => 
    [name] => Donation__c
    [queryable] => 1
    [recordTypeInfos] => Array
        (
            [0] => stdClass Object
                (
                    [available] => 1
                    [defaultRecordTypeMapping] => 1
                    [name] => Master
                    [recordTypeId] => 012000000000000AAA
                )

        )

    [replicateable] => 1
    [retrieveable] => 1
    [searchLayoutable] => 1
    [searchable] => 1
    [triggerable] => 1
    [undeletable] => 1
    [updateable] => 1
    [urlDetail] => https://na3.salesforce.com/{ID}
    [urlEdit] => https://na3.salesforce.com/{ID}/e
    [urlNew] => https://na3.salesforce.com/a08/e
)
