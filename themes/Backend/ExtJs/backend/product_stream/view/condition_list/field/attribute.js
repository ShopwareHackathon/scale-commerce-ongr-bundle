
Ext.define('Shopware.apps.ProductStream.view.condition_list.field.Attribute', {

    extend: 'Ext.form.FieldContainer',
    layout: { type: 'vbox', align: 'stretch' },
    mixins: [ 'Ext.form.field.Base' ],
    height: 70,
    value: undefined,
    attributeField: null,

    initComponent: function() {
        var me = this;
        me.items = me.createItems();
        me.callParent(arguments);
    },

    createItems: function() {
        var me = this;

        return [
            me.createOperatorSelection(),
            me.createValueField(),
            me.createBetweenContainer()
        ];
    },

    createBetweenContainer: function() {
        var me = this;

        me.betweenContainer = Ext.create('Ext.container.Container', {
            layout: { type: 'hbox', align: 'stretch' },
            hidden: true,
            items: [ me.createFromField(), me.createToField() ]
        });
        return me.betweenContainer;
    },

    createFromField: function() {
        var me = this;

        me.fromField = Ext.create('Ext.form.field.Number', {
            fieldLabel: 'from',
            flex: 1,
            listeners: {
                change: function() {
                    me.toField.setMinValue(me.fromField.getValue() -1);
                }
            }
        });
        return me.fromField;
    },

    createToField: function() {
        var me = this;

        me.toField = Ext.create('Ext.form.field.Number', {
            labelWidth: 50,
            fieldLabel: 'to',
            padding: '0 0 0 10',
            flex: 1,
            listeners: {
                change: function() {
                    me.fromField.setMaxValue(me.toField.getValue() + 1);
                }
            }
        });
        return me.toField;
    },

    createOperatorSelection: function () {
        var me = this;

        var store = Ext.create('Ext.data.Store', {
            fields: [ 'name', 'value' ],
            data: [
                { name: 'equals', value: '=' },
                { name: 'not equals', value: '!=' },
                { name: 'less than', value: '<' },
                { name: 'less than equals', value: '<=' },
                { name: 'between', value: 'BETWEEN' },
                { name: 'greater than', value: '>' },
                { name: 'greater than equals', value: '>=' },
                { name: 'in', value: 'IN' },
                { name: 'starts with', value: 'STARTS_WITH' },
                { name: 'ends with', value: 'ENDS_WITH' },
                { name: 'like', value: 'CONTAINS' }
            ]
        });

        me.operatorSelection = Ext.create('Ext.form.field.ComboBox', {
            store: store,
            fieldLabel: 'Operator',
            displayField: 'name',
            valueField: 'value',
            allowBlank: false,
            listeners: {
                change: function(field, value) {
                    if (value == 'BETWEEN') {
                        me.betweenContainer.show();
                        me.valueField.hide();
                    } else {
                        me.betweenContainer.hide();
                        me.valueField.show();
                    }
                }
            }
        });

        return me.operatorSelection;
    },

    createValueField: function () {
        var me = this;

        me.valueField = Ext.create('Ext.form.field.Text', {
            fieldLabel: 'Value'
        });

        return me.valueField;
    },

    getValue: function() {
        return this.value;
    },

    setValue: function(value) {
        var me = this;

        me.value = value;
        if (Ext.isObject(value)) {
            me.attributeField = value.field;

            me.operatorSelection.setValue(value.operator);

            if (value.operator == 'BETWEEN') {
                me.fromField.setValue(value.value.min);
                me.toField.setValue(value.value.max);
            } else {
                me.valueField.setValue(value.value);
            }
        }
    },

    getSubmitData: function() {
        var value = {
            field: this.attributeField,
            operator: this.operatorSelection.getValue(),
            value: this.valueField.getValue()
        };

        if (value.operator == 'BETWEEN') {
            value.value = {
                min: this.fromField.getValue(),
                max: this.toField.getValue()
            }
        }

        var result = {};
        result[this.name] = value;
        return result;
    }
});