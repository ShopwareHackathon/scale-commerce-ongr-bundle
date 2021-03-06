
Ext.define('Shopware.apps.ProductStream.view.condition_list.field.ReleaseDate', {

    extend: 'Ext.form.FieldContainer',
    layout: { type: 'hbox', align: 'stretch' },
    mixins: [ 'Ext.form.field.Base' ],
    height: 30,
    value: undefined,

    initComponent: function() {
        var me = this;
        me.items = me.createItems();
        me.callParent(arguments);
    },

    createItems: function() {
        var me = this;
        return [
            me.createDirectionField(),
            me.createDayField()
        ];
    },

    createDirectionField: function() {
        var me = this;

        me.directionStore = Ext.create('Ext.data.Store', {
            fields: ['value', 'label'],
            data: [
                { value: 'past', label: 'Past' },
                { value: 'future', label: 'Future' }
            ]
        });

        me.directionField = Ext.create('Ext.form.field.ComboBox', {
            allowBlank: false,
            fieldLabel: 'Direction',
            value: 'past',
            displayField: 'label',
            valueField: 'value',
            store: me.directionStore
        });

        return me.directionField;
    },

    createDayField: function() {
        var me = this;

        me.dayField = Ext.create('Ext.form.field.Number', {
            labelWidth: 30,
            fieldLabel: 'days',
            allowBlank: false,
            minValue: 1,
            value: 1,
            padding: '0 0 0 10',
            flex: 1
        });
        return me.dayField;
    },

    getValue: function() {
        return this.value;
    },

    setValue: function(value) {
        var me = this;

        me.value = value;

        if (!Ext.isObject(value)) {
            me.directionField.setValue('past');
            me.dayField.setValue(1);
            return;
        }

        if (value.hasOwnProperty('direction')) {
            me.directionField.setValue(value.direction);
        }
        if (value.hasOwnProperty('days')) {
            me.dayField.setValue(value.days);
        }
    },

    getSubmitData: function() {
        var value = {};

        value[this.name] = {
            direction: this.directionField.getValue(),
            days: this.dayField.getValue()
        };
        return value;
    },

    validate: function() {

        return true;
    }
});