Vue.component('static-page-default', {
	template: `<cms-default title="Static Page" :breadcrumb="breadcrumb" :buttons="buttons">
	<div v-if="loading" class="text-center p-5">
		<b-spinner></b-spinner>
	</div>
	<div class="card" v-else>
		<modal-static-create :selectbox="selectbox"></modal-static-create>
		<div class="table table-hover mb-0">
			<thead class="d-flex px-0 col-12">
				<th class="col-6 col-lg-8">Title</th>
				<th class="col-3 col-lg-2">Status</th>
				<th class="col-3 col-lg-2">Actions</th>
			</thead>
			<div v-if="staticPages.length > 0">
				<static-page
					v-for="(page, offset) in staticPages"
					:key="page.id"
					:offset="offset" 
					:nice-name="page.title"
					:children-count="staticPages.length"
					:page="page" 
					:list="staticPagesInstances"
					:level="0"
					:current-page-id="page.id"
					:parent-id="null" 
					@refresh="sync"
				></static-page>
			</div>
			<div v-else class="col-12 text-center py-3">
				<p>There are no static pages. Start by creating one.</p>
			</div>
		</div>
	</div>
</cms-default>`,
	data() {
		return {
			breadcrumb: [
				{
					label: 'Dashboard',
					href: link('Homepage:default')
				},
				{
					label: 'Static Pages',
					href: link('StaticPage:default')
				}
			],
			buttons: [
				{
					variant: 'primary',
					label: 'Create',
					icon: 'fa-plus',
					action: 'modal',
					target: 'modal-static-create',
				}
			],
			loading: true,
			selectbox: [],
			staticPagesInstances: {},
			staticPages: [],
		}
	},
	mounted() {
		this.sync();
		eventBus.$on('update-static-pages', (form, responseJson) => {
			if (form.parentId === null) {
				this.sync();
			} else {
				this.staticPagesInstances[form.parentId].$emit('sync', form.parentId, true);
				this.syncSelectbox();
			}
		});
	},
	methods: {
		syncSelectbox() {
			axiosApi.get('static-page/static-pages-as-tree').then(req => {
				this.selectbox = [...req.data, {value: null, text: '--- root ---'}];
			});
		},
		sync() {
			this.loading = true;
			axiosApi.get('static-page/static-pages-tree').then(req => {
				this.loading = false;
				let data = req.data;
				this.staticPages = data.staticPages;
			});
			this.syncSelectbox();
		}
	}
});

Vue.component('static-page', {
	props: ['page', 'parentId', 'currentPageId', 'offset', 'childrenCount', 'list', 'niceName', 'level'],
	template: `<div class="col-12 pl-0 pr-0">
	<tr class="d-flex">
		<td class="d-flex col-6 col-lg-8">
			<a @click.prevent="fetchChildren(currentPageId)" href="#" :style="{'margin-left': (level * 10) + 'px'}">
				<b-icon v-if="!loading && page.isChildren" class="my-auto" :icon="iconClasses"></b-icon>
				<template v-else-if="!page.isChildren"></template>
				<b-spinner small v-else></b-spinner>
			</a>
			<a :href="link('StaticPage:detail', {id: page.id})" :class="nameClasses">
				{{ page.title }}
			</a>
		</td>
		<td class="col-3 col-lg-2 text-center">
			<b-badge pill :variant="page.active ? 'primary' : 'danger'">{{ page.active ? 'active' : 'inactive' }}</b-badge>
		</td>
		<td class="col-3 col-lg-2">
			<div class="text-center">
				<b-btn @click.prevent="createChild(page.id)" variant="success" size="sm" title="Add static page">
					<b-icon icon="plus"></b-icon>
				</b-btn>
				<b-btn variant="warning" size="sm" title="Edit" :href="link('StaticPage:detail', {id: page.id})">
					<b-icon icon="pencil"></b-icon>
				</b-btn>
			</div>
		</td>
	</tr>
	<tr class="d-flex" v-for="(child, offset) in children_">
		<keep-alive>
			<static-page
				v-if="showChildren"
				:key="child.id"
				:offset="offset"
				:parent-id="page.id"
				:current-page-id="child.id"
				:nice-name="niceName+'>>'+child.name"
				:children-count="children_.length"
				:list="list"
				:level="level + 1"
				:page="child">
			</static-page>
		</keep-alive>
	</tr>
</div>`,
	data() {
		return {
			showChildren: false,
			children_: [],
			loading: false,
			fetched: false,
		}
	},
	computed: {
		iconClasses() {
			return this.showChildren ? 'caret-down' : 'caret-right'
		},
		nameClasses() {
			return {
				'has-children': this.page.children,
				'pl-3': !this.page.children
			}
		}
	},
	created() {
		this.list[this.currentPageId] = this;
	},
	mounted() {
		this.$on('sync', (myUUID, isFirst = false) => {
			if (isFirst) {
				this.page.children = true;
				this.showChildren = true;
			}
			this.fetched = false;
			this.fetchChildren(myUUID, true);
			if (this.parentId !== null) {
				this.$parent.$emit('sync', this.parentId);
			}
		});
	},
	methods: {
		createChild(parentUuid) {
			eventBus.$emit('static-create-child', parentUuid);
		},
		fetchChildren(parent, sync = false) {
			if (this.fetched) {
				this.showChildren = !this.showChildren;
			} else {
				this.loading = true;
				return axiosApi(`static-page/static-pages-tree?parentId=${parent}`)
					.then(data => {
						this.children_ = data.data.staticPages;
						this.fetched = true;
						if (!sync) this.showChildren = !this.showChildren;
					}).finally(() => this.loading = false);
			}
		}
	}
});


Vue.component('modal-static-create', {
	props: ['selectbox'],
	template: `<b-modal id="modal-static-create" title="Create new static page" @shown="loadParents()" hide-footer>
	<b-form ref="form" @submit.prevent="createPage" autocomplete="off">
		<b-form-group>
			<template v-slot:label>Title <span class="text-danger">*</span></template>
			<b-form-input v-model="form.title" type="text" required></b-form-input>
			<div class="invalid-feedback">Title is required</div>
		</b-form-group>
		<b-form-group>
			<template v-slot:label>Slug <span class="text-danger">*</span></template>
			<b-form-input v-model="form.slug" type="text" :disabled="slug.checking" required></b-form-input>
			<div class="invalid-feedback">Slug is required</div>
			<div v-if="slug.checking">
				<b-spinner small></b-spinner>&nbsp;Checking...
			</div>
			<div v-if="slug.exist" class="text-danger">
				Slug already exist!
			</div>
		</b-form-group>
		<b-form-group label="Parent page">
			<b-form-select v-model="form.parentId" :options="parents"></b-form-select>
		</b-form-group>
		<div class="text-right">
			<b-btn size="sm" variant="primary" @click="createPage()">
				<template v-if="isCreating">
					<b-spinner small class="mx-2"></b-spinner>
				</template>
				<template v-else>
					Create
				</template>
			</b-btn>
		</div>
	</b-form>
</b-modal>`,
	data() {
		return {
			isCreating: false,
			parents: [],
			form: {
				title: '',
				slug: '',
				parentId: null,
			},
			slug: {
				checking: false,
				exist: false
			}
		}
	},
	mounted() {
		eventBus.$on('static-create-child', (parentUuid) => {
			this.form.parentId = parentUuid;
			this.$bvModal.show('modal-static-create');
		});
	},
	methods: {
		loadParents() {
			this.parents = [...this.selectbox]
		},
		createPage() {
			let form = this.$refs.form;
			if (form.checkValidity()) {
				this.isCreating = true;
				axiosApi.post('static-page', this.form)
					.then((req) => {
						this.$bvModal.hide('modal-static-create');
						eventBus.$emit('update-static-pages', JSON.parse(JSON.stringify(this.form)), req.data);
						this.form = {
							title: '',
							parentId: null,
						}

					})
					.finally(() => this.isCreating = false)
			} else {
				form.classList.add('was-validated');
			}
		},
		checkSlug() {
			if (this.slug.checking === false) {
				this.slug.checking = true;
				axiosApi(`static-page/check-unique-slug?slug=${this.form.slug}`)
					.then(data => {
						this.slug.checking = false;
						this.slug.exist = data.data.exist;
						this.form.slug = data.data.slug;
					});
			}
		}
	},
	watch: {
		'form.title': function () {
			this.form.slug = this.form.title;
			this.checkSlug();
		},
		'form.slug': function () {
			this.checkSlug();
		}
	}
});
