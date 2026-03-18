#include <stdio.h>
#include <stdlib.h>

// Struttura per un nodo della lista di adiacenza
typedef struct Nodo {
    int n;
    struct Nodo* next;
	} Nodo;

// Struttura per rappresentare un grafo con liste di adiacenza
typedef struct {
    int num_nodi;
    Nodo** liste_adiacenza;
	} Grafo;

// Funzione per creare un grafo con n nodi
Grafo* creaGrafo(int num_nodi) {
    Grafo* grafo = (Grafo*)malloc(sizeof(Grafo));
    grafo->num_nodi = num_nodi;
    grafo->liste_adiacenza = (Nodo**)malloc(num_nodi * sizeof(Nodo*));
    for(int i = 0; i < num_nodi; i++) {
        grafo->liste_adiacenza[i] = NULL;
    }
    return grafo;
	}

// Funzione per creare un nuovo nodo della lista di adiacenza
Nodo* creaNodo(int n) {
    Nodo* nuovoNodo = (Nodo*)malloc(sizeof(Nodo));
    nuovoNodo->n = n;
    nuovoNodo->next = NULL;
    return nuovoNodo;
	}

// Funzione per aggiungere un arco da u a v
void aggiungiArco(Grafo* grafo, int u, int v) {
    Nodo* nuovoNodo = creaNodo(v);
    nuovoNodo->next = grafo->liste_adiacenza[u];
    grafo->liste_adiacenza[u] = nuovoNodo;
	}

// Funzione per stampare il grafo
void stampaGrafo(Grafo* grafo) {
    for(int i = 0; i < grafo->num_nodi; i++) {
        Nodo* corrente = grafo->liste_adiacenza[i];
        printf("Nodo %d:", i);
        while(corrente != NULL) {
            printf(" -> %d", corrente->n);
            corrente = corrente->next;
        }
        printf("\n");
    }
	}

// Funzione per creare un sottografo indotto da un insieme di nodi
Grafo* sottografoIndotto(Grafo* grafo, int* S, int dim_S) {
    Grafo* sottografo = creaGrafo(grafo->num_nodi);
    int* presente = (int*)calloc(grafo->num_nodi, sizeof(int));
    for(int i = 0; i < dim_S; i++) presente[S[i]] = 1;

    for(int i = 0; i < grafo->num_nodi; i++) {
        if(presente[i]) {
            Nodo* corrente = grafo->liste_adiacenza[i];
            while(corrente != NULL) {
                if(presente[corrente->n]) {
                    aggiungiArco(sottografo, i, corrente->n);
                }
                corrente = corrente->next;
            }
        }
    }
    free(presente);
    return sottografo;
	}

int main() {
    int num_nodi, num_archi, u, v;

    printf("Inserire il numero di nodi: ");
    scanf("%d", &num_nodi);

    Grafo* grafo = creaGrafo(num_nodi);

    printf("Inserire il numero di archi: ");
    scanf("%d", &num_archi);

    printf("Inserire gli archi nel formato (u v):\n");
    for(int i = 0; i < num_archi; i++) {
        scanf("%d %d", &u, &v);
        aggiungiArco(grafo, u, v);
    }

    printf("\nGrafo originale:\n");
    stampaGrafo(grafo);

    int dim_S;
    printf("\nInserire la dimensione dell'insieme S: ");
    scanf("%d", &dim_S);

    int* S = (int*)malloc(dim_S * sizeof(int));

    printf("Inserire gli elementi di S:\n");
    for(int i = 0; i < dim_S; i++) {
        scanf("%d", &S[i]);
    }

    Grafo* sottografo = sottografoIndotto(grafo, S, dim_S);

    printf("\nSottografo indotto:\n");
    stampaGrafo(sottografo);

    free(S);
    free(grafo->liste_adiacenza);
    free(grafo);
    free(sottografo->liste_adiacenza);
    free(sottografo);

    return 0;
	}
