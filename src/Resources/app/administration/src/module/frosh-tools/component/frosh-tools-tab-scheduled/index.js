import template from './template.twig';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('frosh-tools-tab-scheduled', {
    template,
    inject: ['repositoryFactory', 'FroshToolsService'],
    mixins: [
        Mixin.getByName('notification')
    ],

    data() {
        return {
            items: null,
            showResetModal: false,
            isLoading: true
        };
    },

    created() {
        this.createdComponent();
    },

    computed: {
        scheduledRepository() {
            return this.repositoryFactory.create('scheduled_task');
        },

        columns() {
            return [
                {
                    property: 'name',
                    label: 'frosh-tools.name',
                    rawData: true,
                    primary: true
                },
                {
                    property: 'runInterval',
                    label: 'frosh-tools.interval',
                    rawData: true,
                    inlineEdit: 'number'
                },
                {
                    property: 'lastExecutionTime',
                    label: 'frosh-tools.lastExecutionTime',
                    rawData: true
                },
                {
                    property: 'nextExecutionTime',
                    label: 'frosh-tools.nextExecutionTime',
                    rawData: true,
                    inlineEdit: 'datetime'
                }
            ];
        }
    },

    methods: {
        async createdComponent() {
            const criteria = new Criteria;
            this.items = await this.scheduledRepository.search(criteria, Shopware.Context.api);
            this.isLoading = false;
        },
        async runTask(item) {
            this.isLoading = true;

            try {
                this.createNotificationInfo({
                    message: this.$tc('frosh-tools.scheduledTaskStarted', 0, {'name': item.name})
                })
                await this.FroshToolsService.runScheduledTask(item.id);
                this.createNotificationSuccess({
                    message: this.$tc('frosh-tools.scheduledTaskSucceed', 0, {'name': item.name})
                })
            } catch (e) {
                this.createNotificationError({
                    message: this.$tc('frosh-tools.scheduledTaskFailed', 0, {'name': item.name})
                })
            }

            this.createdComponent();
        }
    }
});
