<script setup>
import ManualCallout from '../components/ManualCallout.vue';
import ManualChapter from '../components/ManualChapter.vue';
import ManualDecision from '../components/ManualDecision.vue';
import ManualFigure from '../components/ManualFigure.vue';
import ManualSection from '../components/ManualSection.vue';
import ManualStep from '../components/ManualStep.vue';
import ManualSteps from '../components/ManualSteps.vue';
import { scenarios } from '../data/scenarios';
</script>

<template>
    <ManualChapter id="scenarios">
        <p class="m-lead">
            Chaque scénario part d’une personne réelle du cabinet et d’une situation précise. Il se lit comme un récit : la situation, les gestes, les décisions à prendre en chemin, puis le résultat visible à l’écran. Les variantes indiquent seulement ce qui change ; elles ne répètent pas toute la procédure.
        </p>
        <div class="m-table-wrap">
            <table class="m-table">
                <thead>
                    <tr>
                        <th>Scénario</th>
                        <th>Profil</th>
                        <th>Niveau</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(story, index) in scenarios" :key="story.id">
                        <td>5.{{ index + 1 }} {{ story.title }}</td>
                        <td>{{ story.profile.name }}</td>
                        <td>{{ story.level }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <ManualSection v-for="(story, index) in scenarios" :id="story.id" :key="story.id" :page-break="index > 0">
            <div class="m-story__profile">
                <span class="m-story__avatar"><i :class="story.profile.icon" aria-hidden="true"></i></span>
                <p>
                    <strong>{{ story.profile.name }}</strong>
                    <span class="m-story__level">{{ story.level }}</span>
                    <br />
                    <small>{{ story.profile.context }}</small>
                </p>
            </div>

            <p class="m-story__phase">Situation</p>
            <p>{{ story.situation }}</p>

            <p class="m-story__phase">Actions</p>
            <ManualSteps>
                <ManualStep v-for="step in story.steps" :key="step.text" :critical="step.critical" :result="step.result">
                    {{ step.text }}
                </ManualStep>
            </ManualSteps>

            <ManualDecision v-for="decision in story.decisions" :key="decision.question" :question="decision.question" :options="decision.options" />

            <ManualFigure v-if="story.figure" :label="story.figure.label" :number="`5.${index + 1}`" :caption="story.figure.caption" :markers="story.figure.markers ?? []" compact />

            <p class="m-story__phase">Résultat</p>
            <p class="m-story__result">{{ story.result }}</p>

            <template v-if="story.variants?.length">
                <p class="m-story__phase">Variantes</p>
                <div class="m-table-wrap">
                    <table class="m-table">
                        <thead>
                            <tr>
                                <th style="width: 32%">Si…</th>
                                <th>Alors…</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="variant in story.variants" :key="variant[0]">
                                <td>{{ variant[0] }}</td>
                                <td>{{ variant[1] }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </template>

            <ManualCallout v-for="callout in story.callouts ?? []" :key="callout.text" :type="callout.type">
                <p>{{ callout.text }}</p>
            </ManualCallout>
        </ManualSection>
    </ManualChapter>
</template>
