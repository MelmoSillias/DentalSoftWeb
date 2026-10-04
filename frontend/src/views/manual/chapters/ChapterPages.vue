<script setup>
import ManualCallout from '../components/ManualCallout.vue';
import ManualChapter from '../components/ManualChapter.vue';
import ManualFigure from '../components/ManualFigure.vue';
import ManualSection from '../components/ManualSection.vue';
import { findSection } from '../data/outline';
import { pageDocs } from '../data/pagesCatalog';

function goTo(id) {
    document.getElementById(id)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}
</script>

<template>
    <ManualChapter id="pages">
        <p class="m-lead">
            Chaque fiche décrit un écran tel qu’il apparaît dans le menu : à quoi il sert, ce que déclenche chaque bouton, comment le logiciel réagit, et les cas où il refuse le geste. Les fiches sont indépendantes : on peut lire uniquement celle de l’écran sur lequel on travaille.
        </p>
        <ManualCallout type="note">
            <p>
                La colonne « Qui » indique les profils qui voient l’action. Une action absente pour votre profil n’est pas une panne : le bouton n’est simplement pas affiché. Le Mode Focus est détaillé au chapitre 3 et n’a pas de fiche ici.
            </p>
        </ManualCallout>

        <ManualSection v-for="(page, index) in pageDocs" :id="page.id" :key="page.id" :page-break="index > 0">
            <dl class="m-sheet__meta">
                <div>
                    <dt>Accès</dt>
                    <dd>{{ page.menu }}</dd>
                </div>
                <div>
                    <dt>Profils</dt>
                    <dd>{{ page.roles }}</dd>
                </div>
                <div>
                    <dt>Adresse</dt>
                    <dd>
                        <span class="m-code">{{ page.route }}</span>
                    </dd>
                </div>
            </dl>

            <p class="m-sheet__purpose">{{ page.purpose }}</p>

            <ManualFigure :label="page.figure.label" :number="`4.${index + 1}`" :caption="page.figure.caption" :markers="page.figure.markers ?? []" />

            <h4>Actions et effets</h4>
            <div class="m-table-wrap">
                <table class="m-table">
                    <thead>
                        <tr>
                            <th style="width: 26%">Action</th>
                            <th>Effet</th>
                            <th style="width: 20%">Qui</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in page.actions" :key="row[0]">
                            <td>{{ row[0] }}</td>
                            <td>{{ row[1] }}</td>
                            <td>{{ row[2] }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <h4>Événements et résultats</h4>
            <div class="m-table-wrap">
                <table class="m-table">
                    <thead>
                        <tr>
                            <th style="width: 34%">Quand…</th>
                            <th>Alors…</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in page.events" :key="row[0]">
                            <td>{{ row[0] }}</td>
                            <td>{{ row[1] }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <h4>Cas particuliers</h4>
            <ul class="m-list">
                <li v-for="item in page.exceptions" :key="item">{{ item }}</li>
            </ul>

            <ManualCallout v-if="page.warning" type="critical">
                <p>{{ page.warning }}</p>
            </ManualCallout>

            <div v-if="page.tech?.length" class="m-technote">
                <p class="m-technote__label">Note technique</p>
                <ul>
                    <li v-for="item in page.tech" :key="item">{{ item }}</li>
                </ul>
            </div>

            <p v-if="page.see?.length" class="m-sheet__see">
                Voir aussi :
                <template v-for="target in page.see" :key="target">
                    <a v-if="findSection(target)" :href="`#${target}`" @click.prevent="goTo(target)">{{ findSection(target).number }} {{ findSection(target).title }}</a>
                </template>
            </p>
        </ManualSection>
    </ManualChapter>
</template>
