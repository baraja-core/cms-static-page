Vue.component('static-page-detail', {
	props: ['id'],
	template: `<div>
	<b-overlay :show="isLoading">
		<b-card>
			<b-row>
				<b-col md="12" lg="6">
					<b-form-group label="Title">
						<b-form-input v-model="form.title"></b-form-input>
					</b-form-group>
				</b-col>
				<b-col md="12" lg="6">
					<b-row>
						<b-col>
							<b-form-group label="Parent">
								<b-form-select  :options="options" v-model="form.parentId"></b-form-select>
							</b-form-group>
						</b-col>
						<b-col cols="2">
							<b-form-group label="Active">
								<b-form-checkbox class="pt-2" switch v-model="form.active"></b-form-checkbox>
							</b-form-group>
						</b-col>
					</b-row>
				</b-col>
			</b-row>
			<b-row>
				<b-col>
					<b-form-group label="Content">
						<b-form-textarea v-model="form.content" rows="16"></b-form-textarea>
					</b-form-group>
				</b-col>
			</b-row>
			<b-btn variant="primary" :disabled="isSaving" @click="save">
				<b-spinner small v-if="isSaving"></b-spinner>
				<template v-else>Save</template>
			</b-btn>
		</b-card>
	</b-overlay>
</div>`,
	data() {
		return {
			options: [],
			isLoading: true,
			isSaving: false,
			form: {}
		}
	},
	mounted() {
		this.sync();
	},
	methods: {
		save() {
			this.isSaving = true;
			axiosApi.post('static-page/detail', {
				...this.form,
				id: this.id
			}).then(this.sync)
				.finally(() => this.isSaving = false)
		},
		sync() {
			this.isLoading = true;
			axiosApi.get('static-page/static-pages-as-tree')
				.then(req => {
					this.options = req.data.filter(item => item.value !== this.id);
					this.options.push({
						value: null,
						text: '--- root page ---'
					})
				});
			axiosApi.get(`static-page/detail?id=${this.id}`)
				.then(req => {
					this.form = req.data.item;
					this.form.parentId = req.data.item.parent ? req.data.item.parent.id : null;
				})
				.finally(() => this.isLoading = false);
		}
	}
});
